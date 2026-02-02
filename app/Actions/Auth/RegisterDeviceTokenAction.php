<?php

namespace App\Actions\Auth;

use App\Models\DeviceToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Registers a device token for push notifications.
 */
class RegisterDeviceTokenAction
{
    /**
     * Execute device token registration.
     *
     * @param  User  $user  The user to register the token for
     * @param  Tenant  $tenant  The tenant context
     * @param  string  $deviceToken  The FCM/APNs device token
     * @param  string  $deviceType  Type of device (ios, android, web)
     * @param  string|null  $deviceName  Human-readable device name
     * @return array{device_token: DeviceToken, was_created: bool} The device token and whether it was newly created
     */
    public function execute(
        User $user,
        Tenant $tenant,
        string $deviceToken,
        string $deviceType,
        ?string $deviceName = null
    ): array {
        // Check if token already exists for this user
        $existingToken = DeviceToken::where('user_id', $user->id)
            ->where('device_token', $deviceToken)
            ->first();

        if ($existingToken) {
            // Update existing token
            $existingToken->update([
                'tenant_id' => $tenant->id,
                'device_name' => $deviceName ?? $existingToken->device_name,
                'device_type' => $deviceType,
                'last_used_at' => now(),
            ]);

            Log::info('Device token updated', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'device_token_id' => $existingToken->id,
                'device_type' => $deviceType,
            ]);

            return ['device_token' => $existingToken, 'was_created' => false];
        }

        // Create new token
        $deviceTokenModel = DeviceToken::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'device_token' => $deviceToken,
            'device_name' => $deviceName ?? $this->generateDeviceName($deviceType),
            'device_type' => $deviceType,
            'last_used_at' => now(),
        ]);

        Log::info('Device token registered', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'device_token_id' => $deviceTokenModel->id,
            'device_type' => $deviceType,
        ]);

        return ['device_token' => $deviceTokenModel, 'was_created' => true];
    }

    /**
     * Remove a device token.
     *
     * @param  User  $user  The user who owns the token
     * @param  string  $deviceToken  The device token to remove
     * @return bool Whether the token was removed
     */
    public function remove(User $user, string $deviceToken): bool
    {
        $deleted = DeviceToken::where('user_id', $user->id)
            ->where('device_token', $deviceToken)
            ->delete();

        if ($deleted > 0) {
            Log::info('Device token removed', [
                'user_id' => $user->id,
                'device_token' => substr($deviceToken, 0, 20).'...',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Remove a device token by ID.
     */
    public function removeById(User $user, int $deviceTokenId): bool
    {
        $token = DeviceToken::where('user_id', $user->id)
            ->where('id', $deviceTokenId)
            ->first();

        if (! $token) {
            return false;
        }

        $token->delete();

        Log::info('Device token removed by ID', [
            'user_id' => $user->id,
            'device_token_id' => $deviceTokenId,
        ]);

        return true;
    }

    /**
     * Generate a default device name based on type.
     */
    protected function generateDeviceName(string $deviceType): string
    {
        $names = [
            'ios' => 'iPhone',
            'android' => 'Android Device',
            'web' => 'Web Browser',
        ];

        return $names[$deviceType] ?? 'Unknown Device';
    }
}
