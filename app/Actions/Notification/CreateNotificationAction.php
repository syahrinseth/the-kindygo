<?php

namespace App\Actions\Notification;

use App\Models\PushNotification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Creates a push notification record in the database.
 */
class CreateNotificationAction
{
    /**
     * Execute notification creation.
     *
     * @param  User  $user  The user to notify
     * @param  Tenant  $tenant  The tenant context
     * @param  string  $type  Notification type (e.g., 'invoice.created', 'payment.received')
     * @param  string  $title  Notification title
     * @param  string  $message  Notification message body
     * @param  array<string, mixed>  $data  Additional data payload
     * @return PushNotification The created notification
     */
    public function execute(
        User $user,
        Tenant $tenant,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): PushNotification {
        $notification = PushNotification::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);

        Log::info('Push notification created', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'type' => $type,
        ]);

        return $notification;
    }

    /**
     * Create multiple notifications for multiple users.
     *
     * @param  iterable<User>  $users  Users to notify
     * @param  Tenant  $tenant  The tenant context
     * @param  string  $type  Notification type
     * @param  string  $title  Notification title
     * @param  string  $message  Notification message body
     * @param  array<string, mixed>  $data  Additional data payload
     * @return int Number of notifications created
     */
    public function executeForMultipleUsers(
        iterable $users,
        Tenant $tenant,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): int {
        $count = 0;

        foreach ($users as $user) {
            $this->execute($user, $tenant, $type, $title, $message, $data);
            $count++;
        }

        return $count;
    }
}
