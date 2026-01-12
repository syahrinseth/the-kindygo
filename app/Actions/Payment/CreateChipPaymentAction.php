<?php

namespace App\Actions\Payment;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SyahrinSeth\ChipLaravel\ChipService;

class CreateChipPaymentAction
{
    /**
     * Create a CHIP payment record and initiate CHIP purchase
     *
     * @param  User  $user  The user making the payment
     * @param  int  $totalAmount  Total payment amount in cents
     * @param  array  $allocation  Invoice allocation map ['invoice_id' => amount_in_cents]
     * @return array ['payment' => Payment, 'checkout_url' => string]
     *
     * @throws Exception
     */
    public function execute(User $user, int $totalAmount, array $allocation): array
    {
        return DB::transaction(function () use ($user, $totalAmount, $allocation) {
            // Validate invoices exist and belong to user
            $invoiceIds = array_keys($allocation);
            $invoices = Invoice::whereIn('id', $invoiceIds)
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->current_tenant_id)
                ->get();

            if ($invoices->count() !== count($invoiceIds)) {
                throw new Exception('One or more invoices not found or do not belong to this user.');
            }

            // Validate invoice statuses - only PENDING and OVERDUE allowed
            $invalidInvoices = $invoices->filter(function ($invoice) {
                return ! in_array($invoice->status, [
                    \App\Enums\InvoiceStatus::PENDING,
                    \App\Enums\InvoiceStatus::OVERDUE,
                ]);
            });

            if ($invalidInvoices->isNotEmpty()) {
                $invalidStatuses = $invalidInvoices->pluck('status')->unique()->map(fn ($s) => $s->value)->implode(', ');
                throw new Exception(
                    'Cannot process payment. Only invoices with status PENDING or OVERDUE can be paid. '.
                    "Found invalid status(es): {$invalidStatuses}"
                );
            }

            // Generate a temporary reference number
            $temporaryReferenceNo = 'CHIP-TEMP-'.strtoupper(uniqid());

            // Create payment record with PENDING status (NO centre_id)
            $payment = Payment::create([
                'tenant_id' => $user->current_tenant_id,
                'user_id' => $user->id,
                'gateway' => Gateway::CHIP,
                'status' => PaymentStatus::PENDING,
                'amount' => $totalAmount,
                'reference_no' => $temporaryReferenceNo,
                'description' => 'Payment for '.count($invoiceIds).' invoice(s)',
                'gateway_payment_data' => [
                    'user_allocation' => $allocation,
                    'created_at' => now()->toISOString(),
                ],
            ]);

            // Attach invoices to payment (without allocation amounts yet - will be done on success)
            $payment->invoices()->attach($invoiceIds, ['amount' => 0]);

            // Calculate centre allocations from invoice selections
            $centreAllocations = [];
            foreach ($invoices as $invoice) {
                $centreId = $invoice->centre_id;
                if (! $centreId) {
                    continue; // Skip invoices without centre
                }

                $invoiceAllocation = $allocation[$invoice->id] ?? 0;

                if (! isset($centreAllocations[$centreId])) {
                    $centreAllocations[$centreId] = 0;
                }
                $centreAllocations[$centreId] += $invoiceAllocation;
            }

            // Attach centres with allocated amounts
            foreach ($centreAllocations as $centreId => $amountInCents) {
                $payment->centres()->attach($centreId, [
                    'allocated_amount' => $amountInCents,
                ]);
            }

            Log::info('Payment created with centre allocations', [
                'payment_id' => $payment->id,
                'centre_allocations' => $centreAllocations,
                'is_multi_centre' => count($centreAllocations) > 1,
            ]);

            // Create CHIP purchase
            try {
                $chipService = new ChipService;

                // Create CHIP product for the total amount
                $product = new \Chip\Model\Product;
                $product->name = 'Payment for '.count($invoiceIds).' invoice(s)';
                $product->price = $totalAmount; // in cents

                $chipPurchase = $chipService->createPurchase(
                    $user->email,
                    [$product],
                    route('chip.success', ['payment' => $payment->id]),
                    route('chip.failure', ['payment' => $payment->id]),
                    route('chip.webhook'),
                    route('chip.cancel', ['payment' => $payment->id]),
                    false, // send_receipt
                    $user->name
                );

                // Update payment with CHIP purchase ID
                $payment->update([
                    'gateway_payment_id' => $chipPurchase->id,
                    'reference_no' => $payment->id,
                    'gateway_payment_data' => array_merge($payment->gateway_payment_data ?? [], [
                        'chip_purchase_created_at' => now()->toISOString(),
                        'chip_purchase_id' => $chipPurchase->id,
                    ]),
                ]);

                Log::info('CHIP payment created successfully', [
                    'payment_id' => $payment->id,
                    'chip_purchase_id' => $chipPurchase->id,
                    'amount' => $totalAmount,
                    'invoice_count' => count($invoiceIds),
                ]);

                return [
                    'payment' => $payment->fresh(),
                    'checkout_url' => $chipPurchase->checkout_url,
                ];

            } catch (Exception $e) {
                Log::error('Failed to create CHIP purchase', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Update payment status to failed
                $payment->update(['status' => PaymentStatus::FAILED]);

                throw new Exception('Failed to create CHIP payment: '.$e->getMessage());
            }
        });
    }
}
