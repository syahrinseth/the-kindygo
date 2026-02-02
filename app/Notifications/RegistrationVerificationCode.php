<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for email verification during mobile registration.
 *
 * Sends a 6-digit verification code to the user's email address.
 */
class RegistrationVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $code
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Verification Code - '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Thank you for registering with '.config('app.name').'.')
            ->line('Your verification code is:')
            ->line('**'.$this->code.'**')
            ->line('This code will expire in 15 minutes.')
            ->line('If you did not create an account, please ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'registration_verification',
            'code' => $this->code,
        ];
    }
}
