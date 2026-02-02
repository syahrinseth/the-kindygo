<?php

use App\Http\Controllers\API\V1\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Notification Routes
|--------------------------------------------------------------------------
|
| These routes handle push notification history for mobile apps including
| listing notifications, marking as read, and getting unread count.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('api.v1.notifications.unread-count');
    Route::put('/{notification}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('api.v1.notifications.mark-as-read');
    Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('api.v1.notifications.mark-all-as-read');
});
