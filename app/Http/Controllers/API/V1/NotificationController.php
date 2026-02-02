<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\PushNotificationResource;
use App\Models\PushNotification;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Notifications
 */
#[Group('Notifications', 'Endpoints for managing push notifications.')]
class NotificationController extends Controller
{
    /**
     * Get list of notifications for the authenticated user.
     *
     * Returns a paginated list of push notifications for the authenticated user.
     * Can be filtered to show only unread notifications.
     */
    #[Endpoint(operationId: 'notifications.index', title: 'List notifications')]
    #[QueryParameter('unread_only', description: 'Only return unread notifications', type: 'bool')]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 20)]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $notifications = PushNotification::where('user_id', $user->id)
            ->when($request->query('unread_only'), function ($query) {
                $query->unread();
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => PushNotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get count of unread notifications.
     *
     * Returns the total count of unread notifications for the authenticated user.
     * Useful for displaying badge counts in the mobile app.
     */
    #[Endpoint(operationId: 'notifications.unreadCount', title: 'Get unread count')]
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = PushNotification::where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Mark a notification as read.
     *
     * Marks a specific notification as read. The user must own this notification.
     */
    #[Endpoint(operationId: 'notifications.markAsRead', title: 'Mark as read')]
    #[PathParameter('notification', description: 'The notification ID', type: 'integer')]
    public function markAsRead(Request $request, PushNotification $notification): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user owns this notification
        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => new PushNotificationResource($notification),
        ]);
    }

    /**
     * Mark all notifications as read.
     *
     * Marks all unread notifications for the authenticated user as read.
     * Returns the count of notifications that were marked as read.
     */
    #[Endpoint(operationId: 'notifications.markAllAsRead', title: 'Mark all as read')]
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $count = PushNotification::where('user_id', $user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => "{$count} notification(s) marked as read.",
        ]);
    }
}
