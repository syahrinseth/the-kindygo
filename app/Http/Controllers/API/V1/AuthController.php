<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\RevokeAllTokensAction;
use App\Actions\Auth\RevokeTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\LoginRequest;
use App\Http\Requests\API\V1\RegisterRequest;
use App\Http\Requests\API\V1\VerifyEmailRequest;
use App\Http\Resources\API\V1\TenantResource;
use App\Http\Resources\API\V1\UserResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Authentication
 */
#[Group('Authentication', 'Endpoints for user authentication, registration, and session management.')]
class AuthController extends Controller
{
    public function __construct(
        protected AuthenticateUserAction $authenticateUser,
        protected RevokeTokenAction $revokeToken,
        protected RevokeAllTokensAction $revokeAllTokens
    ) {}

    /**
     * Authenticate user and return access token.
     *
     * Authenticates a user with their email and password. If a tenant_id is provided,
     * the token will be scoped to that tenant. Returns user data, tenant info, and access token.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'auth.login', title: 'Login')]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authenticateUser->execute(
            email: $request->validated('email'),
            password: $request->validated('password'),
            tenantId: $request->validated('tenant_id'),
            deviceName: $request->validated('device_name'),
            requireEmailVerification: true
        );

        if (! $result->isSuccessful()) {
            $statusCode = match ($result->errorCode) {
                'email_not_verified' => 403,
                'no_tenant_access' => 403,
                default => 401,
            };

            return response()->json([
                'success' => false,
                'message' => $result->message,
                'error_code' => $result->errorCode,
                'email_verified' => $result->emailVerified,
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $result->message,
            'data' => [
                'user' => new UserResource($result->user->load(['profile', 'userAddress'])),
                'tenant' => new TenantResource($result->tenant),
                'token' => $result->tokenResult->toArray(),
            ],
        ]);
    }

    /**
     * Register a new user.
     *
     * Creates a new parent user account and associates them with the specified tenant.
     * An email verification code will be sent to the provided email address.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'auth.register', title: 'Register')]
    public function register(RegisterRequest $request): JsonResponse
    {
        // Registration logic will be implemented with RegisterUserAction
        // For now, return a placeholder response
        return response()->json([
            'success' => false,
            'message' => 'Registration endpoint not yet implemented.',
        ], 501);
    }

    /**
     * Log the user out (revoke current token).
     *
     * Revokes the current access token, ending the user's session on this device.
     */
    #[Endpoint(operationId: 'auth.logout', title: 'Logout')]
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->revokeToken->executeCurrentToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Log the user out from all devices (revoke all tokens).
     *
     * Revokes all access tokens for the user, ending all active sessions across all devices.
     */
    #[Endpoint(operationId: 'auth.logoutAll', title: 'Logout from all devices')]
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = $this->revokeAllTokens->execute($user);

        return response()->json([
            'success' => true,
            'message' => "Successfully logged out from {$count} device(s).",
        ]);
    }

    /**
     * Verify user email with verification code.
     *
     * Verifies the user's email address using a 6-digit code sent to their email.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'auth.verifyEmail', title: 'Verify email')]
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        // Email verification logic will be implemented
        return response()->json([
            'success' => false,
            'message' => 'Email verification endpoint not yet implemented.',
        ], 501);
    }

    /**
     * Resend email verification code.
     *
     * Sends a new 6-digit verification code to the user's email address.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'auth.resendVerification', title: 'Resend verification code')]
    public function resendVerification(Request $request): JsonResponse
    {
        // Resend verification logic will be implemented
        return response()->json([
            'success' => false,
            'message' => 'Resend verification endpoint not yet implemented.',
        ], 501);
    }
}
