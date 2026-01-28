<?php

namespace App\Actions\Payment\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use SyahrinSeth\ChipLaravel\ChipService;

class ChipGatewayAction implements PaymentGatewayInterface
{
    public function __construct(
        protected ChipService $chipService
    ) {}

    /**
     * Execute CHIP payment
     */
    public function execute(
        User $user,
        int $totalAmount,
        array $invoices,
        ?array $userAllocation = null,
        array $additionalData = []
    ): PaymentResult {
        return DB::transaction(function () use ($user, $totalAmount, $invoices, $userAllocation) {
            $tenantId = $user->currentTenant()?->id;

            if (! $tenantId) {
                throw new \RuntimeException('User does not have a current tenant.');
            }

            // 1. Load invoice models from invoice IDs
            $invoiceIds = array_column($invoices, 'id');
            $invoiceModels = Invoice::withoutGlobalScope(TenantScope::class)
                ->whereIn('id', $invoiceIds)
                ->where('tenant_id', $tenantId)
                ->get();

            // 2. Generate temporary reference
            $temporaryReferenceNo = 'CHIP-TEMP-'.strtoupper(uniqid());

            // 3. Create payment record with PENDING status
            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'gateway' => Gateway::CHIP,
                'status' => PaymentStatus::PENDING,
                'amount' => $totalAmount,
                'reference_no' => $temporaryReferenceNo,
                'description' => 'Payment for '.count($invoices).' invoice(s)',
                'gateway_payment_data' => [
                    'user_allocation' => $userAllocation,
                    'created_at' => now()->toISOString(),
                ],
            ]);

            // 4. Attach invoices (amount will be set on success)
            $payment->invoices()->withoutGlobalScope(TenantScope::class)->attach($invoiceIds, ['amount' => 0]);

            // 5. Calculate and attach centre allocations
            $this->attachCentreAllocations($payment, $invoiceModels, $userAllocation);

            // 6. Create CHIP purchase
            try {
                $checkoutUrl = $this->createChipPurchase($payment, $user, $totalAmount, count($invoices));

                return PaymentResult::success(
                    payment: $payment->fresh(),
                    checkoutUrl: $checkoutUrl,
                    allocationSummary: [],
                    requiresRedirect: true,
                    message: 'CHIP payment created successfully'
                );

            } catch (Exception $e) {
                Log::error('Failed to create CHIP purchase', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $payment->update(['status' => PaymentStatus::FAILED]);

                throw new Exception('Failed to create CHIP payment: '.$e->getMessage());
            }
        });
    }

    /**
     * CHIP gateway requires redirect to external checkout
     */
    public function requiresRedirect(): bool
    {
        return true;
    }

    /**
     * CHIP gateway supports webhook callbacks
     */
    public function supportsWebhook(): bool
    {
        return true;
    }

    /**
     * Calculate and attach centre allocations to payment
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $invoices
     */
    protected function attachCentreAllocations(Payment $payment, $invoices, ?array $userAllocation): void
    {
        $centreAllocations = [];

        foreach ($invoices as $invoice) {
            if (! $invoice->centre_id) {
                continue;
            }

            $invoiceAllocation = $userAllocation[$invoice->id] ?? 0;

            if (! isset($centreAllocations[$invoice->centre_id])) {
                $centreAllocations[$invoice->centre_id] = 0;
            }
            $centreAllocations[$invoice->centre_id] += $invoiceAllocation;
        }

        foreach ($centreAllocations as $centreId => $amountInCents) {
            $payment->centres()->withoutGlobalScope(TenantScope::class)->attach($centreId, [
                'allocated_amount' => $amountInCents,
            ]);
        }

        Log::info('Payment created with centre allocations', [
            'payment_id' => $payment->id,
            'centre_allocations' => $centreAllocations,
            'is_multi_centre' => count($centreAllocations) > 1,
        ]);
    }

    /**
     * Create CHIP purchase and return checkout URL
     */
    protected function createChipPurchase(Payment $payment, User $user, int $totalAmount, int $invoiceCount): string
    {
        $product = new \Chip\Model\Product;
        $product->name = 'Payment for '.$invoiceCount.' invoice(s)';
        $product->price = $totalAmount;

        // Generate signed URLs for callbacks
        $chipPurchase = $this->chipService->createPurchase(
            $user->email,
            [$product],
            URL::signedRoute('payments.chip.success', ['payment' => $payment->id], absolute: true),
            URL::signedRoute('payments.chip.failure', ['payment' => $payment->id], absolute: true),
            route('payments.chip.webhook', absolute: true),
            URL::signedRoute('payments.chip.cancel', ['payment' => $payment->id], absolute: true),
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
            'invoice_count' => $invoiceCount,
        ]);

        return $chipPurchase->checkout_url;
    }
}
