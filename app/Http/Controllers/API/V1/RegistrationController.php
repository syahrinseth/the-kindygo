<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Auth\GenerateTokenAction;
use App\Actions\Auth\VerifyEmailForRegistrationAction;
use App\Actions\Registration\CompleteParentRegistrationAction;
use App\Actions\Registration\CreateChildrenForParentAction;
use App\Actions\Registration\RegisterDeviceTokenDuringSignupAction;
use App\Actions\Registration\RegisterParentBasicInfoAction;
use App\Actions\Registration\UpdateParentDetailsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\RegisterStep1Request;
use App\Http\Requests\API\V1\RegisterStep2Request;
use App\Http\Requests\API\V1\RegisterStep3Request;
use App\Http\Requests\API\V1\RegisterStep4Request;
use App\Http\Requests\API\V1\VerifyRegistrationEmailRequest;
use App\Http\Resources\API\V1\RegistrationProgressResource;
use App\Http\Resources\API\V1\TenantResource;
use App\Http\Resources\API\V1\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RegistrationVerificationCode;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Multi-step Parent Registration Controller.
 *
 * This controller handles the multi-step registration workflow exclusively for parent users.
 * Users registering through this flow are automatically assigned the "Parent" role.
 *
 * Registration Flow:
 * 1. POST /register/step-1 - Basic info (name, email, password, phone, tenant, centres)
 * 2. POST /register/verify-email - Verify email with 6-digit code
 * 3. POST /register/step-2 - Profile details (address, photos, office info) [Authenticated]
 * 4. POST /register/step-3 - Add children information (optional) [Authenticated]
 * 5. POST /register/step-4 - Accept terms and complete registration [Authenticated]
 *
 * Note: This registration flow is for parents only. Staff, admin, and other user types
 * should be invited/created through the admin panel or other mechanisms.
 *
 * @tags Registration
 */
#[Group('Registration', 'Endpoints for multi-step parent registration workflow. Parents registering will be assigned the Parent role automatically.')]
class RegistrationController extends Controller
{
    public function __construct(
        protected RegisterParentBasicInfoAction $registerBasicInfo,
        protected VerifyEmailForRegistrationAction $verifyEmail,
        protected UpdateParentDetailsAction $updateDetails,
        protected CreateChildrenForParentAction $createChildren,
        protected CompleteParentRegistrationAction $completeRegistration,
        protected RegisterDeviceTokenDuringSignupAction $registerDeviceToken,
        protected GenerateTokenAction $generateToken
    ) {}

    /**
     * Step 1: Register basic parent information.
     *
     * Creates a new parent user account with basic information and sends a verification
     * code to the provided email address. Returns a temporary token for use in
     * the email verification step.
     *
     * New users are automatically assigned the "Parent" role. This endpoint is
     * exclusively for parent registration - staff and admin users should be
     * invited through the admin panel.
     *
     * If the user already exists with unverified email, their information will
     * be updated and a new verification code will be sent.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'registration.step1', title: 'Registration Step 1 - Parent Basic Info')]
    public function step1(RegisterStep1Request $request): JsonResponse
    {
        $validated = $request->validated();

        // Find tenant by slug
        $tenant = Tenant::where('slug', $validated['tenant_slug'])->firstOrFail();

        // Check if user already exists
        $existingUser = User::where('email', $validated['email'])->first();

        // If user exists and has verified email, they can't re-register
        if ($existingUser && $existingUser->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'This email address is already registered. Please login instead.',
                'error_code' => 'email_already_verified',
            ], 422);
        }

        // Execute registration action
        $result = $this->registerBasicInfo->execute($validated, $tenant, $existingUser);
        $user = $result['user'];

        // Register device token (with graceful error handling)
        $deviceTokenResult = $this->registerDeviceToken->execute(
            user: $user,
            tenant: $tenant,
            deviceToken: $validated['device_token'] ?? null,
            deviceType: $validated['device_type'] ?? null,
            deviceName: $validated['device_name'] ?? null
        );

        // Generate verification code and temporary token
        $verificationData = $this->verifyEmail->generateCode($user);

        // Send verification email
        $user->notify(new RegistrationVerificationCode($verificationData['code']));

        // Build response
        $response = [
            'success' => true,
            'message' => 'Registration started. Please check your email for the verification code.',
            'data' => RegistrationProgressResource::withData($user, [
                'temporary_token' => $verificationData['temporary_token'],
                'tenant' => new TenantResource($tenant),
                'email_sent_to' => $user->email,
            ]),
        ];

        // Add device token warning if applicable
        if ($deviceTokenResult['warning']) {
            $response['device_token_warning'] = $deviceTokenResult['warning'];
        }

        return response()->json($response, 201);
    }

    /**
     * Verify email with 6-digit code.
     *
     * Verifies the user's email address using the 6-digit code sent to their email.
     * On successful verification, returns a full access token for subsequent API calls.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'registration.verifyEmail', title: 'Verify Registration Email')]
    public function verifyEmail(VerifyRegistrationEmailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Verify the code
        $result = $this->verifyEmail->verify(
            temporaryToken: $validated['temporary_token'],
            code: $validated['code']
        );

        if (! $result['success']) {
            $statusCode = match ($result['error_code']) {
                'invalid_token', 'token_mismatch', 'user_not_found' => 401,
                'code_expired' => 410,
                'invalid_code' => 422,
                default => 400,
            };

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'error_code' => $result['error_code'],
            ], $statusCode);
        }

        /** @var User $user */
        $user = $result['user'];
        $tenant = $user->currentTenant();

        // Generate full access token (only if tenant exists)
        $tokenResult = null;
        if ($tenant) {
            $tokenResult = $this->generateToken->execute(
                user: $user,
                tenant: $tenant,
                deviceName: 'Registration'
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'user' => new UserResource($user->load(['profile', 'userAddress'])),
                'tenant' => $tenant ? new TenantResource($tenant) : null,
                'token' => $tokenResult?->toArray(),
                'registration' => [
                    'current_step' => $user->getCurrentRegistrationStep(),
                    'next_step' => 2,
                    'is_complete' => false,
                ],
            ],
        ]);
    }

    /**
     * Step 2: Update parent details and upload documents.
     *
     * Updates the user's address, occupation, office information, and uploads
     * required documents (profile photo, MyKad image, immunization card).
     *
     * Requires Bearer token authentication (from verifyEmail step).
     */
    #[Endpoint(operationId: 'registration.step2', title: 'Registration Step 2')]
    public function step2(RegisterStep2Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Verify email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before proceeding.',
                'error_code' => 'email_not_verified',
            ], 403);
        }

        // Check they're at the right step (1 or less, since they need to complete step 2)
        if ($user->getCurrentRegistrationStep() >= 4) {
            return response()->json([
                'success' => false,
                'message' => 'Registration is already complete.',
                'error_code' => 'registration_complete',
            ], 422);
        }

        $validated = $request->validated();

        // Execute update action
        $this->updateDetails->execute($user, $validated);

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Parent details updated successfully.',
            'data' => RegistrationProgressResource::withData($user, [
                'next_step' => 3,
            ]),
        ]);
    }

    /**
     * Step 3: Add children information (optional).
     *
     * Creates child records and links them to the parent user. This step is
     * optional - users can pass an empty children array to skip adding children
     * at this time.
     *
     * Requires Bearer token authentication.
     */
    #[Endpoint(operationId: 'registration.step3', title: 'Registration Step 3')]
    public function step3(RegisterStep3Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Verify email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before proceeding.',
                'error_code' => 'email_not_verified',
            ], 403);
        }

        // Check they've completed step 2
        if ($user->getCurrentRegistrationStep() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete step 2 before proceeding.',
                'error_code' => 'step_not_complete',
            ], 422);
        }

        // Check registration isn't already complete
        if ($user->getCurrentRegistrationStep() >= 4) {
            return response()->json([
                'success' => false,
                'message' => 'Registration is already complete.',
                'error_code' => 'registration_complete',
            ], 422);
        }

        $validated = $request->validated();

        // Execute create children action (handles empty array as skip)
        $this->createChildren->execute($user, $validated['children'] ?? []);

        $user->refresh();

        $childrenCount = count($validated['children'] ?? []);
        $message = $childrenCount > 0
            ? "Successfully added {$childrenCount} child(ren)."
            : 'Children step skipped.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => RegistrationProgressResource::withData($user, [
                'next_step' => 4,
                'children_added' => $childrenCount,
            ]),
        ]);
    }

    /**
     * Step 4: Accept agreements and complete registration.
     *
     * Accepts Terms & Conditions and Letter of Undertaking to complete the
     * registration process. Both agreements must be accepted.
     *
     * Requires Bearer token authentication.
     */
    #[Endpoint(operationId: 'registration.step4', title: 'Registration Step 4')]
    public function step4(RegisterStep4Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Verify email is verified
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before proceeding.',
                'error_code' => 'email_not_verified',
            ], 403);
        }

        // Check they've completed step 3
        if ($user->getCurrentRegistrationStep() < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete previous steps before proceeding.',
                'error_code' => 'step_not_complete',
            ], 422);
        }

        // Check registration isn't already complete
        if ($user->isRegistrationComplete()) {
            return response()->json([
                'success' => false,
                'message' => 'Registration is already complete.',
                'error_code' => 'registration_complete',
            ], 422);
        }

        $validated = $request->validated();

        // Execute complete registration action
        $this->completeRegistration->execute($user, $validated, $request);

        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Registration completed successfully! Welcome to '.config('app.name').'.',
            'data' => [
                'user' => new UserResource($user->load(['profile', 'userAddress'])),
                'tenant' => $user->currentTenant() ? new TenantResource($user->currentTenant()) : null,
                'registration' => [
                    'current_step' => 4,
                    'is_complete' => true,
                ],
            ],
        ]);
    }

    /**
     * Get current registration progress.
     *
     * Returns the current registration step and completion status for the
     * authenticated user.
     *
     * Requires Bearer token authentication.
     */
    #[Endpoint(operationId: 'registration.status', title: 'Registration Status')]
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => RegistrationProgressResource::withData($user, [
                'tenant' => $user->currentTenant() ? new TenantResource($user->currentTenant()) : null,
            ]),
        ]);
    }
}
