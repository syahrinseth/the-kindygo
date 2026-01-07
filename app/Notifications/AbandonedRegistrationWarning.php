<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AbandonedRegistrationWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public User $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $daysRemaining = 5;
        $currentStep = $this->user->getCurrentRegistrationStep();
        $tenant = $this->user->tenant;

        $registrationUrl = $tenant
            ? route('tenant.register.form', [
                'tenant' => $tenant->slug,
                'step' => $currentStep,
                'email' => $this->user->email,
            ])
            : route('login');

        return (new MailMessage)
            ->subject('Complete Your Registration - Action Required')
            ->greeting('Hello '.$this->user->name.'!')
            ->line('We noticed that you started registering with us but haven\'t completed the process yet.')
            ->line("You\'re currently on step {$currentStep} of 4.")
            ->line("Your registration will expire in {$daysRemaining} days if not completed.")
            ->action('Complete Registration Now', $registrationUrl)
            ->line('If you have any questions or need assistance, please don\'t hesitate to contact us.')
            ->line('Thank you for choosing our services!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'registration_warning',
            'current_step' => $this->user->getCurrentRegistrationStep(),
            'days_remaining' => 5,
            'message' => 'Your registration will expire soon. Please complete it to access your account.',
        ];
    }
}
