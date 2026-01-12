<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MultiInvoicePaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment,
        public array $allocationSummary
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
        $paymentUrl = route('filament.app.resources.payments.view', [
            'tenant' => $this->payment->tenant,
            'record' => $this->payment->id,
        ]);

        $totalInvoices = $this->allocationSummary['total_invoices'] ?? 0;
        $fullyPaidCount = $this->allocationSummary['fully_paid_count'] ?? 0;
        $partiallyPaidCount = $this->allocationSummary['partially_paid_count'] ?? 0;

        // Get invoice numbers (comma-separated)
        $invoiceNumbers = collect($this->allocationSummary['allocation_details'] ?? [])
            ->pluck('invoice_number')
            ->join(', ');

        $message = (new MailMessage)
            ->subject("Payment Receipt - {$totalInvoices} Invoice(s) Processed")
            ->greeting("Hello {$notifiable->name},")
            ->line('Your payment has been successfully processed!');

        // Payment summary
        $message->line('**Payment Summary:**');
        $message->line('Payment Amount: RM '.number_format($this->payment->amount / 100, 2));
        $message->line("Payment Reference: {$this->payment->reference_no}");
        $message->line('Payment Date: '.($this->payment->paid_at ?? $this->payment->created_at)->format('M d, Y h:i A'));
        $message->line('Payment Method: '.strtoupper($this->payment->gateway));

        // Invoice summary
        $message->line('**Invoice Summary:**');
        $message->line("Total Invoices: {$totalInvoices}");

        if ($fullyPaidCount === $totalInvoices) {
            $message->line('✓ All invoices fully paid');
        } else {
            if ($fullyPaidCount > 0) {
                $message->line("✓ {$fullyPaidCount} invoice(s) fully paid");
            }
            if ($partiallyPaidCount > 0) {
                $message->line("⚬ {$partiallyPaidCount} invoice(s) partially paid");
            }
        }

        $message->line("Invoice Numbers: {$invoiceNumbers}");

        $message->action('View Payment Details', $paymentUrl);

        $message->line('Thank you for your payment!');

        if ($partiallyPaidCount > 0) {
            $message->line('Note: Some invoices have remaining balances. Please check your account for details.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'payment_amount' => $this->payment->amount,
            'payment_reference' => $this->payment->reference_no,
            'total_invoices' => $this->allocationSummary['total_invoices'] ?? 0,
            'fully_paid_count' => $this->allocationSummary['fully_paid_count'] ?? 0,
            'partially_paid_count' => $this->allocationSummary['partially_paid_count'] ?? 0,
        ];
    }
}
