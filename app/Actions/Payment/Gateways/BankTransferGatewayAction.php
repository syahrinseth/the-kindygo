<?php

namespace App\Actions\Payment\Gateways;

use App\Actions\Payment\AllocatePaymentToInvoicesAction;
use App\Actions\Payment\ProcessPaymentAllocationAction;
use App\Actions\Payment\RecordLedgerEntriesAction;
use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Notifications\MultiInvoicePaymentReceiptNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankTransferGatewayAction implements PaymentGatewayInterface
{
    public function __construct(
        protected AllocatePaymentToInvoicesAction $allocatePaymentToInvoices,
        protected RecordLedgerEntriesAction $recordLedgerEntries,
        protected ProcessPaymentAllocationAction $processPaymentAllocation,
    ) {}

    /**
     * Process bank transfer payment with immediate PAID status.
     *
     * @param  User  $user  The user making the payment
     * @param  int  $totalAmount  Total payment amount in cents
     * @param  array  $invoices  Array of invoice data with IDs
     * @param  array|null  $userAllocation  Optional: ['invoice_id' => amount_in_cents]
     * @param  array  $additionalData  Must include 'reference_no', optional 'payment_proof'
     */
    public function execute(
        User $user,
        int $totalAmount,
        array $invoices,
        ?array $userAllocation = null,
        array $additionalData = []
    ): PaymentResult {
        return DB::transaction(function () use ($user, $totalAmount, $invoices, $userAllocation, $additionalData) {
            try {
                $tenantId = $user->currentTenant()?->id;

                if (! $tenantId) {
                    throw new \RuntimeException('User does not have a current tenant.');
                }

                Log::info('Processing bank transfer payment', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'amount' => $totalAmount,
                    'invoice_count' => count($invoices),
                ]);

                // Fetch selected invoices, preserving the order from invoice_ids
                $invoiceIds = array_column($invoices, 'id');
                $invoiceCollection = Invoice::withoutGlobalScope(TenantScope::class)
                    ->whereIn('id', $invoiceIds)
                    ->where('tenant_id', $tenantId)
                    ->get()
                    ->sortBy(function ($invoice) use ($invoiceIds) {
                        return array_search($invoice->id, $invoiceIds);
                    })
                    ->values();

                // Create payment record with PAID status (immediate payment)
                $payment = Payment::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'gateway' => Gateway::BANK_TRANSFER,
                    'reference_no' => $additionalData['reference_no'] ?? null,
                    'status' => PaymentStatus::PAID,
                    'amount' => $totalAmount,
                    'description' => 'Payment for '.count($invoiceIds).' invoice(s)',
                    'paid_at' => now(),
                ]);

                Log::info('Bank transfer payment created', [
                    'payment_id' => $payment->id,
                    'status' => PaymentStatus::PAID->value,
                ]);

                // Handle payment proof upload for bank transfer
                if (isset($additionalData['payment_proof'])) {
                    $payment->addMedia($additionalData['payment_proof'])
                        ->toMediaCollection('payment_proof', 'private');

                    Log::info('Payment proof uploaded', [
                        'payment_id' => $payment->id,
                    ]);
                }

                // Allocate payment to invoices using FIFO or user-defined allocation
                $allocationSummary = $this->allocatePaymentToInvoices->execute(
                    $payment,
                    $invoiceCollection,
                    $totalAmount,
                    $userAllocation
                );

                Log::info('Payment allocated to invoices', [
                    'payment_id' => $payment->id,
                    'fully_paid_count' => $allocationSummary['fully_paid_count'],
                    'partially_paid_count' => $allocationSummary['partially_paid_count'],
                ]);

                // Update invoice statuses based on allocation
                $this->processPaymentAllocation->updateInvoiceStatuses($payment, $invoiceCollection);

                // Attach centres from invoices that received payment
                $centreAllocations = [];
                foreach ($allocationSummary['allocation_details'] as $detail) {
                    $invoice = $invoiceCollection->firstWhere('id', $detail['invoice_id']);
                    if ($invoice && $invoice->centre_id) {
                        if (! isset($centreAllocations[$invoice->centre_id])) {
                            $centreAllocations[$invoice->centre_id] = 0;
                        }
                        $centreAllocations[$invoice->centre_id] += $detail['allocated_amount'];
                    }
                }

                foreach ($centreAllocations as $centreId => $allocatedAmount) {
                    $payment->centres()->withoutGlobalScope(TenantScope::class)->attach($centreId, [
                        'allocated_amount' => $allocatedAmount,
                    ]);
                }

                Log::info('Centre allocations recorded', [
                    'payment_id' => $payment->id,
                    'centre_count' => count($centreAllocations),
                ]);

                // Record ledger entries for bank transfer (immediate payment)
                $this->recordLedgerEntries->execute($payment, $allocationSummary);

                Log::info('Ledger entries recorded', [
                    'payment_id' => $payment->id,
                ]);

                // Send receipt notification for bank transfer
                $user->notify(new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary));

                Log::info('Payment receipt notification sent', [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                ]);

                return PaymentResult::success(
                    payment: $payment,
                    allocationSummary: $allocationSummary,
                    requiresRedirect: false,
                    message: 'Bank transfer payment processed successfully.'
                );
            } catch (\Exception $e) {
                Log::error('Bank transfer payment failed', [
                    'user_id' => $user->id,
                    'amount' => $totalAmount,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Bank transfer does not require redirect to external gateway.
     */
    public function requiresRedirect(): bool
    {
        return false;
    }

    /**
     * Bank transfer does not support webhooks (manual verification).
     */
    public function supportsWebhook(): bool
    {
        return false;
    }
}
