<?php

namespace App\Actions\Notification;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications to multiple devices at once via FCM multicast.
 *
 * Note: Requires kreait/laravel-firebase package to be installed.
 * Install with: composer require kreait/laravel-firebase
 */
class SendMulticastNotificationAction
{
    /**
     * Send a notification to all devices of all users in a tenant.
     *
     * @param  Tenant  $tenant  The tenant whose users should receive the notification
     * @param  string  $type  Notification type
     * @param  string  $title  Notification title
     * @param  string  $message  Notification message body
     * @param  array<string, mixed>  $data  Additional data payload
     * @return array{notifications_created: int, devices_sent: int, devices_failed: int}
     */
    public function execute(
        Tenant $tenant,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): array {
        // Get all active device tokens for this tenant
        $deviceTokens = DeviceToken::where('tenant_id', $tenant->id)
            ->whereNotNull('device_token')
            ->get();

        if ($deviceTokens->isEmpty()) {
            Log::info('No device tokens found for tenant multicast', [
                'tenant_id' => $tenant->id,
            ]);

            return [
                'notifications_created' => 0,
                'devices_sent' => 0,
                'devices_failed' => 0,
            ];
        }

        // Group tokens by user to create notifications
        $tokensByUser = $deviceTokens->groupBy('user_id');
        $notificationsCreated = 0;
        $devicesSent = 0;
        $devicesFailed = 0;

        // Create notifications for each user
        foreach ($tokensByUser as $userId => $userTokens) {
            $notification = PushNotification::create([
                'user_id' => $userId,
                'tenant_id' => $tenant->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'is_read' => false,
            ]);

            $notificationsCreated++;

            // Send to all devices for this user
            $result = $this->sendToDevices($userTokens, $title, $message, $data);

            $devicesSent += $result['sent'];
            $devicesFailed += $result['failed'];

            if ($result['sent'] > 0) {
                $notification->markAsSent();
            }
        }

        Log::info('Multicast notification sent', [
            'tenant_id' => $tenant->id,
            'notifications_created' => $notificationsCreated,
            'devices_sent' => $devicesSent,
            'devices_failed' => $devicesFailed,
        ]);

        return [
            'notifications_created' => $notificationsCreated,
            'devices_sent' => $devicesSent,
            'devices_failed' => $devicesFailed,
        ];
    }

    /**
     * Send to specific user IDs within a tenant.
     *
     * @param  Tenant  $tenant  The tenant context
     * @param  array<int>  $userIds  User IDs to notify
     * @param  string  $type  Notification type
     * @param  string  $title  Notification title
     * @param  string  $message  Notification message body
     * @param  array<string, mixed>  $data  Additional data payload
     * @return array{notifications_created: int, devices_sent: int, devices_failed: int}
     */
    public function executeForUsers(
        Tenant $tenant,
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): array {
        // Get device tokens for specified users
        $deviceTokens = DeviceToken::where('tenant_id', $tenant->id)
            ->whereIn('user_id', $userIds)
            ->whereNotNull('device_token')
            ->get();

        if ($deviceTokens->isEmpty()) {
            return [
                'notifications_created' => 0,
                'devices_sent' => 0,
                'devices_failed' => 0,
            ];
        }

        // Group tokens by user
        $tokensByUser = $deviceTokens->groupBy('user_id');
        $notificationsCreated = 0;
        $devicesSent = 0;
        $devicesFailed = 0;

        foreach ($tokensByUser as $userId => $userTokens) {
            $notification = PushNotification::create([
                'user_id' => $userId,
                'tenant_id' => $tenant->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'is_read' => false,
            ]);

            $notificationsCreated++;

            $result = $this->sendToDevices($userTokens, $title, $message, $data);

            $devicesSent += $result['sent'];
            $devicesFailed += $result['failed'];

            if ($result['sent'] > 0) {
                $notification->markAsSent();
            }
        }

        return [
            'notifications_created' => $notificationsCreated,
            'devices_sent' => $devicesSent,
            'devices_failed' => $devicesFailed,
        ];
    }

    /**
     * Send notification to a collection of device tokens.
     *
     * @param  Collection<int, DeviceToken>  $deviceTokens  Device tokens to send to
     * @param  string  $title  Notification title
     * @param  string  $body  Notification body
     * @param  array<string, mixed>  $data  Additional data payload
     * @return array{sent: int, failed: int}
     */
    protected function sendToDevices(
        Collection $deviceTokens,
        string $title,
        string $body,
        array $data = []
    ): array {
        if (! $this->isFirebaseConfigured()) {
            Log::warning('Firebase not configured for multicast');

            return ['sent' => 0, 'failed' => $deviceTokens->count()];
        }

        $sent = 0;
        $failed = 0;
        $tokens = $deviceTokens->pluck('device_token')->toArray();

        try {
            $messaging = app('firebase.messaging');

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            $report = $messaging->sendMulticast($message, $tokens);

            $sent = $report->successes()->count();
            $failed = $report->failures()->count();

            // Clean up invalid tokens
            foreach ($report->failures()->getItems() as $failure) {
                if ($failure->error() && $failure->error()->messageTargetWasInvalid()) {
                    $failedToken = $tokens[$failure->target()->index()] ?? null;
                    if ($failedToken) {
                        DeviceToken::where('device_token', $failedToken)->delete();
                        Log::info('Removed invalid FCM token', [
                            'token' => substr($failedToken, 0, 20).'...',
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Multicast notification failed', [
                'error' => $e->getMessage(),
                'token_count' => count($tokens),
            ]);
            $failed = count($tokens);
        }

        return ['sent' => $sent, 'failed' => $failed];
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
