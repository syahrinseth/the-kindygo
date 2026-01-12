<?php

namespace App\Notifications;

use App\Models\LetterOfUndertaking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewLetterOfUndertakingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LetterOfUndertaking $letter
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
        $tenant = $this->letter->tenant;
        $agreementUrl = url('/agreement/pending');

        return (new MailMessage)
            ->subject("New Agreement Required for {$tenant->name}")
            ->line("A new Letter of Undertaking (Version {$this->letter->version}) has been published by {$tenant->name}.")
            ->line('You must review and agree to this updated agreement to continue using the platform.')
            ->action('Review and Agree', $agreementUrl)
            ->line('Your access to the parent portal will be blocked until you accept this agreement.')
            ->line('If you have any questions, please contact the centre administration.');
    }
}
