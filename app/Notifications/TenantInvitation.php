<?php

namespace App\Notifications;

use App\Models\TenantInvitation as TenantInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TenantInvitationModel $invitation
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
        $acceptUrl = url("/invitations/{$this->invitation->token}");

        return (new MailMessage)
            ->subject("Invitation to join {$this->invitation->tenant->name}")
            ->line("You've been invited to join {$this->invitation->tenant->name} as a {$this->invitation->role}.")
            ->line('Click the button below to accept the invitation:')
            ->action('Accept Invitation', $acceptUrl)
            ->line('This invitation will expire in 7 days.')
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
