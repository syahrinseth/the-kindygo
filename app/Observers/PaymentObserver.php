<?php

namespace App\Observers;

use App\Actions\Payment\RecordLedgerEntriesAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\MultiInvoicePaymentReceiptNotification;

class PaymentObserver
{
    public function __construct(
        protected RecordLedgerEntriesAction $recordLedgerEntries,
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
}
