<?php

namespace App\Observers;

use App\Actions\Payment\ProcessPaymentAllocationAction;
use App\Actions\Payment\RecordLedgerEntriesAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\MultiInvoicePaymentReceiptNotification;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    public function __construct(
        protected RecordLedgerEntriesAction $recordLedgerEntries,
        protected ProcessPaymentAllocationAction $processPaymentAllocation,
    ) {}

    /**
     * Handle the Payment "updating" event.
     * Record ledger entries when payment status changes to PAID.
     */
    public function updating(Payment $payment): void
    {
        // Check if status is being changed to PAID
        if ($payment->isDirty('status') && $payment->status === PaymentStatus::PAID) {
            // Load payment with invoices and their items
            $payment->load(['invoices.invoiceItems']);

            // Safety check: ensure invoices are attached
            if ($payment->invoices->isEmpty()) {
                Log::warning('Payment marked as PAID but no invoices attached', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                ]);

                return; // Skip processing
            }

            // Build allocation summary from pivot data
            $allocationDetails = [];
            $fullyPaidCount = 0;
            $partiallyPaidCount = 0;

            foreach ($payment->invoices as $invoice) {
                $allocatedAmount = $invoice->pivot->amount;
                $totalPaid = $invoice->getTotalPaid();
                $fullyPaid = $totalPaid >= $invoice->total;

                $allocationDetails[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'allocated_amount' => $allocatedAmount,
                    'fully_paid' => $fullyPaid,
                ];

                if ($fullyPaid) {
                    $fullyPaidCount++;
                } else {
                    $partiallyPaidCount++;
                }
            }

            $allocationSummary = [
                'fully_paid_count' => $fullyPaidCount,
                'partially_paid_count' => $partiallyPaidCount,
                'total_invoices' => count($payment->invoices),
                'allocation_details' => $allocationDetails,
            ];

            // Record ledger entries
            $this->recordLedgerEntries->execute($payment, $allocationSummary);

            // Send notification to user
            if ($payment->user) {
                $payment->user->notify(new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary));
            }
        }
    }

    /**
     * Handle the Payment "updated" event.
     * Update invoice statuses when payment status changes to PAID.
     *
     * This event fires AFTER the model is saved, ensuring the payment status
     * is committed to the database. This allows getTotalPaid() to correctly
     * count this payment when calculating invoice totals.
     */
    public function updated(Payment $payment): void
    {
        // Check if status was changed to PAID
        if ($payment->wasChanged('status') && $payment->status === PaymentStatus::PAID) {
            // Load invoices if not already loaded
            $invoices = $payment->invoices;

            // Safety check: ensure invoices are attached
            if ($invoices->isEmpty()) {
                Log::warning('Payment status changed to PAID but no invoices attached', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id,
                ]);

                return; // Skip processing
            }

            // Update invoice statuses now that payment is marked as paid
            // This ensures getTotalPaid() includes this payment in calculations
            $this->processPaymentAllocation->updateInvoiceStatuses($payment, $invoices);

            Log::info('Invoice statuses updated via payment observer', [
                'payment_id' => $payment->id,
                'invoice_count' => $invoices->count(),
                'invoice_ids' => $invoices->pluck('id')->toArray(),
            ]);
        }
    }
}
