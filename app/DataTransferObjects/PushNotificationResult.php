<?php

namespace App\DataTransferObjects;

use App\Models\PushNotification;

/**
 * Data Transfer Object for push notification send results.
 */
class PushNotificationResult
{
    public function __construct(
        public bool $success,
        public ?PushNotification $notification,
        public int $sentCount,
        public int $failedCount,
        public ?string $message = null,
        public ?string $errorCode = null,
    ) {}

    /**
     * Create a successful send result.
     */
    public static function sent(
        PushNotification $notification,
        int $sentCount,
        int $failedCount = 0
    ): self {
        return new self(
            success: $sentCount > 0,
            notification: $notification,
            sentCount: $sentCount,
            failedCount: $failedCount,
            message: "Notification sent to {$sentCount} device(s).",
        );
    }

    /**
     * Create a result when no device tokens are available.
     */
    public static function noDeviceTokens(PushNotification $notification): self
    {
        return new self(
            success: false,
            notification: $notification,
            sentCount: 0,
            failedCount: 0,
            message: 'No device tokens found for user.',
            errorCode: 'no_device_tokens',
        );
    }

    /**
     * Create a failed send result.
     */
    public static function failed(
        PushNotification $notification,
        string $message,
        string $errorCode = 'send_failed'
    ): self {
        return new self(
            success: false,
            notification: $notification,
            sentCount: 0,
            failedCount: 1,
            message: $message,
            errorCode: $errorCode,
        );
    }

    /**
     * Create a result when Firebase is not configured.
     */
    public static function notConfigured(PushNotification $notification): self
    {
        return new self(
            success: false,
            notification: $notification,
            sentCount: 0,
            failedCount: 0,
            message: 'Push notification service is not configured.',
            errorCode: 'not_configured',
        );
    }

    /**
     * Check if the notification was successfully sent.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if there were any partial failures.
     */
    public function hasPartialFailure(): bool
    {
        return $this->success && $this->failedCount > 0;
    }

    /**
     * Get the total number of devices targeted.
     */
    public function totalDevices(): int
    {
        return $this->sentCount + $this->failedCount;
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'message' => $this->message,
            'sent_count' => $this->sentCount,
            'failed_count' => $this->failedCount,
        ];

        if ($this->errorCode) {
            $data['error_code'] = $this->errorCode;
        }

        if ($this->notification) {
            $data['notification_id'] = $this->notification->id;
        }

        return $data;
    }
}
