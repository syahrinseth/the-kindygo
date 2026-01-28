<?php

namespace App\Actions\Payment;

use App\DataTransferObjects\PaymentResult;
use App\Enums\Gateway;
use App\Factories\PaymentGatewayFactory;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MakePaymentAction
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory
    ) {}

    /**
     * Execute payment through the specified gateway.
     *
     * @param  User  $user  The user making the payment
     * @param  Gateway  $gateway  Payment gateway to use (CHIP, BANK_TRANSFER, etc.)
     * @param  int  $totalAmount  Total payment amount in cents
     * @param  array  $invoices  Array of invoice data with IDs
     * @param  array|null  $userAllocation  Optional: ['invoice_id' => amount_in_cents]
     * @param  array  $additionalData  Gateway-specific additional data (reference_no, payment_proof, etc.)
     */
    public function execute(
        User $user,
        Gateway $gateway,
        int $totalAmount,
        array $invoices,
        ?array $userAllocation = null,
        array $additionalData = []
    ): PaymentResult {
        try {
            Log::info('Starting payment processing', [
                'user_id' => $user->id,
                'gateway' => $gateway->value,
                'amount' => $totalAmount,
                'invoice_count' => count($invoices),
                'has_user_allocation' => ! empty($userAllocation),
            ]);

            // Get the appropriate gateway action
            $gatewayAction = $this->gatewayFactory->make($gateway);

            // Execute payment through the gateway
            $result = $gatewayAction->execute(
                user: $user,
                totalAmount: $totalAmount,
                invoices: $invoices,
                userAllocation: $userAllocation,
                additionalData: $additionalData
            );

            Log::info('Payment processing completed', [
                'user_id' => $user->id,
                'gateway' => $gateway->value,
                'payment_id' => $result->payment?->id,
                'success' => $result->success,
                'requires_redirect' => $result->requiresRedirect,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'user_id' => $user->id,
                'gateway' => $gateway->value,
                'amount' => $totalAmount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return failure result
            return PaymentResult::failure(
                message: 'Payment processing failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Check if the given gateway requires redirect to external payment page.
     */
    public function requiresRedirect(Gateway $gateway): bool
    {
        $gatewayAction = $this->gatewayFactory->make($gateway);

        return $gatewayAction->requiresRedirect();
    }

    /**
     * Check if the given gateway supports webhook notifications.
     */
    public function supportsWebhook(Gateway $gateway): bool
    {
        $gatewayAction = $this->gatewayFactory->make($gateway);

        return $gatewayAction->supportsWebhook();
    }
}
