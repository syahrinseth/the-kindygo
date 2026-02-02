<?php

namespace App\Actions\Notification;

use App\DataTransferObjects\PushNotificationResult;
use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sends a push notification to a user's device(s) via FCM.
 *
 * Note: Requires kreait/laravel-firebase package to be installed.
 * Install with: composer require kreait/laravel-firebase
 */
class SendPushNotificationAction
{
    public function __construct(
        protected CreateNotificationAction $createNotificationAction
    ) {}

    /**
     * Send a push notification to a specific user.
     *
     * @param  PushNotification  $notification  The notification to send
     * @param  bool  $saveToDatabase  Whether to mark the notification as sent
     * @return PushNotificationResult Result of the push notification attempt
     */
    public function execute(
        PushNotification $notification,
        bool $saveToDatabase = true
    ): PushNotificationResult {
        $user = $notification->user;
        $tenant = $notification->tenant;

        // Get active device tokens for this user and tenant
        $deviceTokens = DeviceToken::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->get();

        if ($deviceTokens->isEmpty()) {
            Log::info('No device tokens found for user', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'notification_id' => $notification->id,
            ]);

            return PushNotificationResult::noDeviceTokens($notification);
        }

        $successCount = 0;
        $failedTokens = [];

        foreach ($deviceTokens as $deviceToken) {
            $sent = $this->sendToDevice(
                $deviceToken,
                $notification->title,
                $notification->message,
                $notification->data ?? []
            );

            if ($sent) {
                $successCount++;
                $deviceToken->touchLastUsed();
            } else {
                $failedTokens[] = $deviceToken->id;
            }
        }

        if ($saveToDatabase && $successCount > 0) {
            $notification->markAsSent();
        }

        Log::info('Push notification sent', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'total_devices' => $deviceTokens->count(),
            'success_count' => $successCount,
            'failed_count' => count($failedTokens),
        ]);

        return PushNotificationResult::sent(
            $notification,
            $successCount,
            count($failedTokens)
        );
    }

    /**
     * Send a push notification to a specific device.
     *
     * @param  DeviceToken  $deviceToken  The device token to send to
     * @param  string  $title  Notification title
     * @param  string  $body  Notification body
     * @param  array<string, mixed>  $data  Additional data payload
     * @return bool Whether the notification was sent successfully
     */
    protected function sendToDevice(
        DeviceToken $deviceToken,
        string $title,
        string $body,
        array $data = []
    ): bool {
        // Check if Firebase is configured
        if (! $this->isFirebaseConfigured()) {
            Log::warning('Firebase is not configured, skipping push notification', [
                'device_token_id' => $deviceToken->id,
            ]);

            return false;
        }

        try {
            $messaging = app('firebase.messaging');

            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $deviceToken->device_token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            $messaging->send($message);

            return true;
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Token is invalid, mark for cleanup
            Log::info('Invalid FCM token, removing', [
                'device_token_id' => $deviceToken->id,
            ]);
            $deviceToken->delete();

            return false;
        } catch (\Throwable $e) {
            Log::error('Failed to send push notification', [
                'device_token_id' => $deviceToken->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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

    /**
     * Send notifications to multiple users.
     *
     * @param  Collection<int, PushNotification>  $notifications  Notifications to send
     * @return array{sent: int, failed: int}
     */
    public function sendMultiple(Collection $notifications): array
    {
        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            $result = $this->execute($notification);

            if ($result->isSuccess()) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
        ];
    }
}
