<?php

namespace App\Actions\Registration;

use App\Actions\Auth\RegisterDeviceTokenAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Registers a device token during the signup process.
 *
 * This action wraps the RegisterDeviceTokenAction to provide graceful
 * error handling during signup - it will warn on failure but not block
 * the registration process.
 */
class RegisterDeviceTokenDuringSignupAction
{
    public function __construct(
        protected RegisterDeviceTokenAction $registerDeviceToken
    ) {}

    /**
     * Execute device token registration during signup.
     *
     * @param  User  $user  The user to register the token for
     * @param  Tenant  $tenant  The tenant context
     * @param  string|null  $deviceToken  The FCM/APNs device token
     * @param  string|null  $deviceType  Type of device (ios, android, web)
     * @param  string|null  $deviceName  Human-readable device name
     * @return array{success: bool, device_token: \App\Models\DeviceToken|null, warning: string|null}
     */
    public function execute(
        User $user,
        Tenant $tenant,
        ?string $deviceToken = null,
        ?string $deviceType = null,
        ?string $deviceName = null
    ): array {
        // If no device token provided, return early with no warning
        if (empty($deviceToken)) {
            return [
                'success' => true,
                'device_token' => null,
                'warning' => null,
            ];
        }

        // Default device type if not provided
        $deviceType = $deviceType ?? 'android';

        try {
            $result = $this->registerDeviceToken->execute(
                user: $user,
                tenant: $tenant,
                deviceToken: $deviceToken,
                deviceType: $deviceType,
                deviceName: $deviceName
            );

            return [
                'success' => true,
                'device_token' => $result['device_token'],
                'warning' => null,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to register device token during signup', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'device_type' => $deviceType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'device_token' => null,
                'warning' => 'Failed to register device token. You can add it later in your profile settings.',
            ];
        }
    }
}
