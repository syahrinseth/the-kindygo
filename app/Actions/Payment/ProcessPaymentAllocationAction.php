<?php

namespace App\Actions\Payment;

use App\Enums\InvoiceStatus;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentAllocationAction
{
    public function __construct(
        protected AllocatePaymentToInvoicesAction $allocatePayment,
        protected RecordLedgerEntriesAction $recordLedger
    ) {}

    /**
     * Process payment allocation from session data or invoice collection.
     * Handles the full payment allocation flow: allocation -> ledger entries -> status updates.
     *
     * @param  Payment  $payment  The payment record (should already be created)
     * @param  Collection  $invoices  Collection of invoices to allocate payment to
     * @param  array|null  $userAllocation  Optional: ['invoice_id' => amount_in_cents]
     * @return array Allocation summary with payment processing details
     */
    public function execute(
        Payment $payment,
        Collection $invoices,
        ?array $userAllocation = null
    ): array {
        return DB::transaction(function () use ($payment, $invoices, $userAllocation) {
            try {
                // Step 1: Allocate payment to invoices with priority-based distribution
                Log::info('Starting payment allocation', [
                    'payment_id' => $payment->id,
                    'total_amount' => $payment->amount,
                    'invoice_count' => $invoices->count(),
                    'strategy' => $userAllocation ? 'user_defined' : 'fifo',
                ]);

                $allocationSummary = $this->allocatePayment->execute(
                    payment: $payment,
                    invoices: $invoices,
                    totalPaymentAmount: $payment->amount,
                    userAllocation: $userAllocation
                );

                Log::info('Payment allocation completed', [
                    'payment_id' => $payment->id,
                    'fully_paid_count' => $allocationSummary['fully_paid_count'],
                    'partially_paid_count' => $allocationSummary['partially_paid_count'],
                    'total_allocated' => $allocationSummary['total_allocated'],
                ]);

                // Step 2: Record ledger entries for audit trail
                $this->recordLedger->execute($payment, $allocationSummary);

                Log::info('Ledger entries recorded', [
                    'payment_id' => $payment->id,
                ]);

                // Step 3: Update invoice statuses
                $this->updateInvoiceStatuses($payment, $invoices);

                return [
                    'success' => true,
                    'payment_id' => $payment->id,
                    'allocation_summary' => $allocationSummary,
                ];
            } catch (\Exception $e) {
                Log::error('Payment allocation processing failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Update invoice statuses after payment allocation.
     * Only updates invoices that are fully paid.
     */
    protected function updateInvoiceStatuses(Payment $payment, Collection $invoices): void
    {
        foreach ($invoices as $invoice) {
            // Reload invoice to get fresh data after allocation
            $invoice->refresh();

            $totalPaid = $invoice->getTotalPaid();

            // Update to PAID status if fully paid
            if ($totalPaid >= $invoice->total && $invoice->status !== InvoiceStatus::PAID) {
                $invoice->update([
                    'status' => InvoiceStatus::PAID,
                ]);

                Log::info('Invoice marked as fully paid', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'total' => $invoice->total,
                    'total_paid' => $totalPaid,
                ]);
            }
        }
    }

    /**
     * Get allocation summary message for display.
     */
    public function getAllocationMessage(array $allocationSummary): string
    {
        $invoiceCount = $allocationSummary['total_invoices'];
        $fullyPaidCount = $allocationSummary['fully_paid_count'];
        $partiallyPaidCount = $allocationSummary['partially_paid_count'];

        $message = "Payment processed successfully for {$invoiceCount} invoice(s)";

        if ($fullyPaidCount > 0 && $partiallyPaidCount > 0) {
            $message .= " ({$fullyPaidCount} fully paid, {$partiallyPaidCount} partially paid)";
        } elseif ($fullyPaidCount === $invoiceCount) {
            $message .= ' (all fully paid)';
        } elseif ($partiallyPaidCount === $invoiceCount) {
            $message .= ' (all partially paid)';
        }

        return $message;
    }
}
