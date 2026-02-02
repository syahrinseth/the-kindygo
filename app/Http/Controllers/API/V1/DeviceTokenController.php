<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Auth\RegisterDeviceTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\RegisterDeviceTokenRequest;
use App\Http\Resources\API\V1\DeviceTokenResource;
use App\Models\DeviceToken;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Device Tokens
 */
#[Group('Device Tokens', 'Endpoints for managing device tokens used for push notifications.')]
class DeviceTokenController extends Controller
{
    public function __construct(
        protected RegisterDeviceTokenAction $registerDeviceToken
    ) {}

    /**
     * Get list of registered device tokens for the authenticated user.
     *
     * Returns all device tokens registered for the authenticated user.
     * Useful for managing push notification subscriptions across devices.
     */
    #[Endpoint(operationId: 'deviceTokens.index', title: 'List device tokens')]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $deviceTokens = $user->deviceTokens()
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => DeviceTokenResource::collection($deviceTokens),
        ]);
    }

    /**
     * Register a new device token for push notifications.
     *
     * Registers a device token (FCM token) for receiving push notifications.
     * If the token already exists, it will be updated with the new device information.
     * Returns 201 for new tokens and 200 for updated tokens.
     */
    #[Endpoint(operationId: 'deviceTokens.store', title: 'Register device token')]
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $tenant = $user->currentTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No current tenant context.',
            ], 422);
        }

        $validated = $request->validated();

        $result = $this->registerDeviceToken->execute(
            user: $user,
            tenant: $tenant,
            deviceToken: $validated['device_token'],
            deviceType: $validated['device_type'],
            deviceName: $validated['device_name'] ?? null
        );

        $statusCode = $result['was_created'] ? 201 : 200;
        $message = $result['was_created']
            ? 'Device token registered successfully.'
            : 'Device token updated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new DeviceTokenResource($result['device_token']),
        ], $statusCode);
    }

    /**
     * Remove a device token.
     *
     * Removes a device token, unsubscribing the device from push notifications.
     * The user must own this device token.
     */
    #[Endpoint(operationId: 'deviceTokens.destroy', title: 'Remove device token')]
    #[PathParameter('deviceToken', description: 'The device token ID', type: 'integer')]
    public function destroy(Request $request, DeviceToken $deviceToken): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user owns this device token
        if ($deviceToken->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Device token not found.',
            ], 404);
        }

        $deviceToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device token removed successfully.',
        ]);
    }
}
