<?php

namespace App\Actions\Notification;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that an FCM token is valid and can receive notifications.
 *
 * Note: Requires kreait/laravel-firebase package to be installed.
 * Install with: composer require kreait/laravel-firebase
 */
class VerifyFcmTokenAction
{
    /**
     * Verify a single device token.
     *
     * @param  DeviceToken  $deviceToken  The device token to verify
     * @return bool Whether the token is valid
     */
    public function execute(DeviceToken $deviceToken): bool
    {
        if (! $this->isFirebaseConfigured()) {
            Log::warning('Firebase not configured, skipping token verification', [
                'device_token_id' => $deviceToken->id,
            ]);

            return false;
        }

        try {
            $messaging = app('firebase.messaging');

            // Attempt to validate the token by sending a dry-run message
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $deviceToken->device_token);

            $messaging->validate($message);

            // Mark as verified
            $deviceToken->markVerified();

            Log::info('FCM token verified', [
                'device_token_id' => $deviceToken->id,
            ]);

            return true;
        } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
            Log::info('FCM token invalid', [
                'device_token_id' => $deviceToken->id,
                'reason' => $e->getMessage(),
            ]);

            // Delete invalid token
            $deviceToken->delete();

            return false;
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            Log::info('FCM token not found', [
                'device_token_id' => $deviceToken->id,
            ]);

            // Delete non-existent token
            $deviceToken->delete();

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM token verification error', [
                'device_token_id' => $deviceToken->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify a token string before registration.
     *
     * @param  string  $token  The FCM token to verify
     * @return bool Whether the token appears valid
     */
    public function verifyTokenString(string $token): bool
    {
        if (! $this->isFirebaseConfigured()) {
            // If Firebase isn't configured, allow registration anyway
            return true;
        }

        try {
            $messaging = app('firebase.messaging');

            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token);

            $messaging->validate($message);

            return true;
        } catch (\Throwable $e) {
            Log::info('FCM token string invalid', [
                'token' => substr($token, 0, 20).'...',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Verify all unverified tokens for cleanup.
     *
     * @param  int|null  $limit  Maximum tokens to verify
     * @return array{verified: int, invalid: int, errors: int}
     */
    public function verifyUnverifiedTokens(?int $limit = 100): array
    {
        $tokens = DeviceToken::whereNull('push_token_verified_at')
            ->oldest()
            ->limit($limit)
            ->get();

        $verified = 0;
        $invalid = 0;
        $errors = 0;

        foreach ($tokens as $token) {
            $result = $this->execute($token);

            if ($result === true) {
                $verified++;
            } elseif (! $token->exists) {
                // Token was deleted due to being invalid
                $invalid++;
            } else {
                $errors++;
            }
        }

        Log::info('Batch token verification completed', [
            'verified' => $verified,
            'invalid' => $invalid,
            'errors' => $errors,
        ]);

        return [
            'verified' => $verified,
            'invalid' => $invalid,
            'errors' => $errors,
        ];
    }

    /**
     * Check if Firebase is configured.
     */
    protected function isFirebaseConfigured(): bool
    {
        try {
            return app()->bound('firebase.messaging');
        } catch (\Throwable) {
            return false;
        }
    }
}
