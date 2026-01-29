<?php

namespace App\Actions\Payment\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Models\User;

class StripeGatewayAction implements PaymentGatewayInterface
{
    /**
     * Stripe gateway is not yet implemented.
     *
     * @throws \RuntimeException
     */
    public function execute(
        User $user,
        int $totalAmount,
        array $invoices,
        ?array $userAllocation = null,
        array $additionalData = []
    ): PaymentResult {
        throw new \RuntimeException('Stripe payment gateway is not yet implemented.');
    }

    public function requiresRedirect(): bool
    {
        return true;
    }

    public function supportsWebhook(): bool
    {
        return true;
    }
}
