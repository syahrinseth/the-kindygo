<?php

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\MultiInvoicePaymentReceiptNotification;
use Illuminate\Support\Facades\DB;

class ProcessMultiInvoicePaymentAction
{
    public function __construct(
        protected AllocatePaymentToInvoicesAction $allocatePaymentToInvoices,
        protected RecordLedgerEntriesAction $recordLedgerEntries,
        protected ProcessPaymentAllocationAction $processPaymentAllocation,
    ) {}

    /**
     * Process multi-invoice payment with FIFO allocation.
     *
     * @param  array  $validated  Validated payment data
     */
    public function execute(User $user, array $validated): Payment
    {
        return DB::transaction(function () use ($user, $validated) {
            $paymentAmount = $validated['payment_amount'];
            $gateway = $validated['gateway'];
            $referenceNo = $validated['reference_no'] ?? null;

            // Fetch selected invoices, preserving the order from invoice_ids
            $invoiceIds = $validated['invoice_ids'];
            $invoices = Invoice::whereIn('id', $invoiceIds)
                ->where('tenant_id', $user->current_tenant_id)
                ->get()
                ->sortBy(function ($invoice) use ($invoiceIds) {
                    return array_search($invoice->id, $invoiceIds);
                })
                ->values();

            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $user->current_tenant_id,
                'user_id' => $user->id,
                'gateway' => $gateway,
                'reference_no' => $referenceNo,
                'status' => $gateway === 'bank_transfer' ? PaymentStatus::PAID : PaymentStatus::PENDING,
                'amount' => $paymentAmount,
                'description' => 'Payment for '.count($validated['invoice_ids']).' invoices',
                'paid_at' => $gateway === 'bank_transfer' ? now() : null,
            ]);

            // Handle payment proof upload for bank transfer
            if ($gateway === 'bank_transfer' && isset($validated['payment_proof'])) {
                $payment->addMedia($validated['payment_proof'])
                    ->toMediaCollection('payment_proof', 'private');
            }

            // Allocate payment to invoices using FIFO
            $allocationSummary = $this->allocatePaymentToInvoices->execute(
                $payment,
                $invoices,
                $paymentAmount
            );

            // Update invoice statuses based on allocation
            $this->processPaymentAllocation->updateInvoiceStatuses($payment, $invoices);

            // Attach centres from invoices that received payment
            $centreAllocations = [];
            foreach ($allocationSummary['allocation_details'] as $detail) {
                $invoice = $invoices->firstWhere('id', $detail['invoice_id']);
                if ($invoice && $invoice->centre_id) {
                    if (! isset($centreAllocations[$invoice->centre_id])) {
                        $centreAllocations[$invoice->centre_id] = 0;
                    }
                    $centreAllocations[$invoice->centre_id] += $detail['allocated_amount'];
                }
            }

            foreach ($centreAllocations as $centreId => $allocatedAmount) {
                $payment->centres()->attach($centreId, [
                    'allocated_amount' => $allocatedAmount,
                ]);
            }

            // Record ledger entries for bank transfer (immediate payment)
            // For CHIP, ledger will be recorded by observer when webhook updates status to PAID
            if ($gateway === 'bank_transfer') {
                $this->recordLedgerEntries->execute($payment, $allocationSummary);

                // Send receipt notification for bank transfer
                $user->notify(new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary));
            }

            return $payment;
        });
    }
}
