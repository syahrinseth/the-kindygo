<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Invoice $invoice,
        public int $daysOverdue
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
        $invoiceUrl = route('filament.app.resources.invoices.view', [
            'tenant' => $this->invoice->tenant,
            'record' => $this->invoice->id,
        ]);

        return (new MailMessage)
            ->subject("URGENT: Overdue Invoice #{$this->invoice->number}")
            ->greeting("Hello {$notifiable->name},")
            ->line('**This is an urgent reminder about your overdue invoice.**')
            ->line('**Invoice Details:**')
            ->line("Invoice Number: #{$this->invoice->number}")
            ->line('Amount: RM '.number_format($this->invoice->total / 100, 2))
            ->line('Due Date: '.$this->invoice->due_at->format('M d, Y'))
            ->line("Days Overdue: {$this->daysOverdue} days")
            ->line("Centre: {$this->invoice->centre->name}")
            ->action('Pay Now', $invoiceUrl)
            ->line('**Please settle this payment immediately to avoid any service interruption.**')
            ->line('Late fees may apply for overdue payments.')
            ->line('If you have any questions or need to arrange a payment plan, please contact your centre administrator immediately.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->number,
            'amount' => $this->invoice->total,
            'due_at' => $this->invoice->due_at,
            'days_overdue' => $this->daysOverdue,
            'centre_name' => $this->invoice->centre->name,
        ];
    }
}
