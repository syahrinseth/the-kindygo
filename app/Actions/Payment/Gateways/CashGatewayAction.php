<?php

namespace App\Actions\Payment\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Models\User;

class CashGatewayAction implements PaymentGatewayInterface
{
    /**
     * Cash gateway is not yet implemented.
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
        throw new \RuntimeException('Cash payment gateway is not yet implemented.');
    }

    public function requiresRedirect(): bool
    {
        return false;
    }

    public function supportsWebhook(): bool
    {
        return false;
    }
}
