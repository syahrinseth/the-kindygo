<?php

namespace App\Actions\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllocatePaymentToInvoicesAction
{
    /**
     * Allocate payment to multiple invoices.
     * Supports both FIFO strategy and user-defined allocation.
     * Within each invoice, distributes payment by item priority.
     *
     * @param  int  $totalPaymentAmount  Amount in cents
     * @param  array|null  $userAllocation  Optional: ['invoice_id' => amount_in_cents]
     * @return array Allocation summary
     */
    public function execute(
        Payment $payment,
        Collection $invoices,
        int $totalPaymentAmount,
        ?array $userAllocation = null
    ): array {
        return DB::transaction(function () use ($payment, $invoices, $totalPaymentAmount, $userAllocation) {
            $remainingPayment = $totalPaymentAmount;
            $fullyPaidCount = 0;
            $partiallyPaidCount = 0;
            $allocationDetails = [];

            // Determine allocation strategy
            $isUserDefined = ! empty($userAllocation);
            $strategy = $isUserDefined ? 'user_defined_priority' : 'fifo_priority';

            if ($isUserDefined) {
                // User-defined allocation: Use user's specified amounts per invoice
                foreach ($userAllocation as $invoiceId => $allocatedAmount) {
                    if ($remainingPayment <= 0 || $allocatedAmount <= 0) {
                        continue;
                    }

                    $invoice = $invoices->firstWhere('id', $invoiceId);
                    if (! $invoice) {
                        continue;
                    }

                    $invoiceBalance = $invoice->getRemainingBalance();
                    if ($invoiceBalance <= 0) {
                        continue; // Skip fully paid invoices
                    }

                    // Ensure we don't allocate more than the invoice balance or remaining payment
                    $actualAllocation = min($allocatedAmount, $invoiceBalance, $remainingPayment);

                    // Process this invoice allocation
                    $result = $this->processInvoiceAllocation($payment, $invoice, $actualAllocation);

                    $allocationDetails[] = $result;
                    $remainingPayment -= $actualAllocation;

                    if ($result['fully_paid']) {
                        $fullyPaidCount++;
                    } else {
                        $partiallyPaidCount++;
                    }
                }
            } else {
                // FIFO strategy: Sort invoices by due_at (oldest first)
                $sortedInvoices = $invoices->sortBy('due_at')->values();

                foreach ($sortedInvoices as $invoice) {
                    if ($remainingPayment <= 0) {
                        break;
                    }

                    $invoiceBalance = $invoice->getRemainingBalance();
                    if ($invoiceBalance <= 0) {
                        continue; // Skip fully paid invoices
                    }

                    // Allocate minimum of remaining payment or invoice balance
                    $allocatedAmount = min($remainingPayment, $invoiceBalance);

                    // Process this invoice allocation
                    $result = $this->processInvoiceAllocation($payment, $invoice, $allocatedAmount);

                    $allocationDetails[] = $result;
                    $remainingPayment -= $allocatedAmount;

                    if ($result['fully_paid']) {
                        $fullyPaidCount++;
                    } else {
                        $partiallyPaidCount++;
                    }
                }
            }

            return [
                'strategy' => $strategy,
                'fully_paid_count' => $fullyPaidCount,
                'partially_paid_count' => $partiallyPaidCount,
                'total_invoices' => $fullyPaidCount + $partiallyPaidCount,
                'total_allocated' => $totalPaymentAmount - $remainingPayment,
                'remaining_unallocated' => $remainingPayment,
                'allocation_details' => $allocationDetails,
            ];
        });
    }

    /**
     * Process allocation for a single invoice.
     * Distributes payment across invoice items by priority.
     */
    protected function processInvoiceAllocation(Payment $payment, Invoice $invoice, int $allocatedAmount): array
    {
        // Check if the invoice is already attached (e.g., from ChipGatewayAction with amount=0)
        $existingPivot = $payment->invoices()
            ->withoutGlobalScope(TenantScope::class)
            ->wherePivot('invoice_id', $invoice->id)
            ->exists();

        if ($existingPivot) {
            // Update existing pivot record (handles idempotency and pre-attached invoices)
            $payment->invoices()
                ->withoutGlobalScope(TenantScope::class)
                ->updateExistingPivot($invoice->id, [
                    'amount' => $allocatedAmount,
                    'updated_at' => now(),
                ]);
        } else {
            // Attach new pivot record
            $payment->invoices()
                ->withoutGlobalScope(TenantScope::class)
                ->attach($invoice->id, [
                    'amount' => $allocatedAmount,
                ]);
        }

        // Allocate payment to invoice items by priority
        $this->allocatePaymentToInvoiceItemsByPriority($invoice, $allocatedAmount);

        // Calculate payment totals for return value
        $totalPaid = $invoice->getTotalPaid();
        $fullyPaid = ($totalPaid >= $invoice->total);

        Log::info('Allocated payment to invoice', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'allocated_amount' => $allocatedAmount,
            'fully_paid' => $fullyPaid,
            'total_paid' => $totalPaid,
            'remaining_balance' => $invoice->total - $totalPaid,
        ]);

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'allocated_amount' => $allocatedAmount,
            'fully_paid' => $fullyPaid,
            'total_paid' => $totalPaid,
            'remaining_balance' => $invoice->total - $totalPaid,
        ];
    }

    /**
     * Allocate payment amount to invoice items by priority.
     * Higher priority items (CRITICAL=4, HIGH=3) get paid first.
     *
     * @param  int  $allocationAmount  Amount in cents to allocate to this invoice
     */
    protected function allocatePaymentToInvoiceItemsByPriority(Invoice $invoice, int $allocationAmount): void
    {
        // Load invoice items with product priority, ordered by priority DESC (highest first), then by ID
        $invoiceItems = $invoice->invoiceItems()
            ->with('product')
            ->leftJoin('products', 'invoice_items.product_id', '=', 'products.id')
            ->orderByRaw('
                CASE
                    WHEN products.priority IS NOT NULL THEN products.priority
                    ELSE 2
                END DESC
            ')
            ->orderBy('invoice_items.id')
            ->select('invoice_items.*') // Only select invoice_items columns to avoid conflicts
            ->get();

        $remainingPayment = $allocationAmount;

        foreach ($invoiceItems as $item) {
            if ($remainingPayment <= 0) {
                break;
            }

            $itemTotal = $item->total;
            $currentPaidAmount = $item->paid_amount ?? 0;
            $outstandingAmount = $itemTotal - $currentPaidAmount;

            if ($outstandingAmount > 0) {
                // Allocate payment to this item
                $paymentForThisItem = min($remainingPayment, $outstandingAmount);
                $newPaidAmount = $currentPaidAmount + $paymentForThisItem;
                $newBalanceAmount = max(0, $itemTotal - $newPaidAmount);

                $item->update([
                    'paid_amount' => $newPaidAmount,
                    'balance_amount' => $newBalanceAmount,
                    'paid' => ($newBalanceAmount == 0),
                ]);

                $remainingPayment -= $paymentForThisItem;
            }
        }
    }
}
