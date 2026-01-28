<?php

namespace App\Contracts;

use App\DataTransferObjects\PaymentResult;
use App\Models\User;

interface PaymentGatewayInterface
{
    /**
     * Execute payment through this gateway
     *
     * @param  User  $user  The user making the payment
     * @param  int  $totalAmount  Total payment amount in cents
     * @param  array  $invoices  Array of Invoice models
     * @param  array|null  $userAllocation  Optional: ['invoice_id' => amount_in_cents]
     * @param  array  $additionalData  Gateway-specific data (reference_no, payment_proof, etc)
     */
    public function execute(
        User $user,
        int $totalAmount,
        array $invoices,
        ?array $userAllocation = null,
        array $additionalData = []
    ): PaymentResult;

    /**
     * Check if this gateway requires a redirect to external checkout
     */
    public function requiresRedirect(): bool;

    /**
     * Check if this gateway supports webhook callbacks
     */
    public function supportsWebhook(): bool;
}
