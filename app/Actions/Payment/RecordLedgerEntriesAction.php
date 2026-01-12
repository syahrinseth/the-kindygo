<?php

namespace App\Actions\Payment;

use App\Models\InvoiceItemsLedger;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class RecordLedgerEntriesAction
{
    /**
     * Record ledger entries for payment allocation.
     * Creates immutable audit records for each invoice item that received payment.
     *
     * @param  array  $allocationSummary  Summary from AllocatePaymentToInvoicesAction
     */
    public function execute(Payment $payment, array $allocationSummary): void
    {
        $ledgerEntries = [];

        // Load payment with invoices and their items
        $payment->load(['invoices.invoiceItems.product']);

        foreach ($payment->invoices as $invoice) {
            // Find allocation details for this invoice
            $invoiceAllocation = collect($allocationSummary['allocation_details'])
                ->firstWhere('invoice_id', $invoice->id);

            if (! $invoiceAllocation) {
                continue;
            }

            foreach ($invoice->invoiceItems as $item) {
                // Only record items that have received payment in this transaction
                // Check if paid_amount increased (comparing with previous state would require tracking)
                // For now, we record all items with paid_amount > 0 when payment is made
                if ($item->paid_amount > 0) {
                    // Get priority from product
                    $priority = $item->product?->priority?->value ?? 2;

                    // Calculate how much was paid in THIS payment
                    // This is tricky - we need to track payment per item in allocation
                    // For now, we'll use the credit_amount from invoice_item updates
                    $creditAmount = $item->paid_amount; // This is cumulative, we need the delta

                    // Better approach: Get the latest ledger balance and calculate delta
                    // Only look at credit entries (payment allocations) to get the previous balance
                    $previousBalance = InvoiceItemsLedger::where('invoice_item_id', $item->id)
                        ->where('credit_amount', '>', 0)
                        ->latest('recorded_at')
                        ->value('balance_amount') ?? $item->total;

                    $creditAmount = $previousBalance - $item->balance_amount;

                    if ($creditAmount > 0) {
                        $ledgerEntries[] = [
                            'tenant_id' => $invoice->tenant_id,
                            'user_id' => $invoice->user_id,
                            'centre_id' => $invoice->centre_id,
                            'ledger_type' => 'payment_allocation',
                            'invoice_id' => $invoice->id,
                            'invoice_item_id' => $item->id,
                            'payment_id' => $payment->id,
                            'child_id' => $item->child_id,
                            'product_id' => $item->product_id,
                            'description' => $item->name ?? $item->description,
                            'debit_amount' => 0, // ✅ Payment entries don't create new charges
                            'credit_amount' => $creditAmount, // ✅ Amount paid in THIS payment
                            'balance_amount' => $item->balance_amount, // ✅ Snapshot after this payment
                            'paid' => $item->paid, // ✅ Is item fully paid now?
                            'priority' => $priority,
                            'reference_data' => json_encode([
                                'payment_id' => $payment->id,
                                'gateway' => $payment->gateway,
                                'reference_no' => $payment->reference_no,
                                'invoice_allocation_amount' => $invoiceAllocation['allocated_amount'],
                                'strategy' => $allocationSummary['strategy'] ?? 'user_defined_priority',
                                'fully_paid' => $invoiceAllocation['fully_paid'],
                                'payment_status' => $payment->status->value,
                            ]),
                            'recorded_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        // Bulk insert for performance
        if (! empty($ledgerEntries)) {
            DB::table('invoice_items_ledgers')->insert($ledgerEntries);
        }
    }
}
