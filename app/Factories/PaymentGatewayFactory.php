<?php

namespace App\Factories;

use App\Actions\Payment\Gateways\BankTransferGatewayAction;
use App\Actions\Payment\Gateways\BillplzGatewayAction;
use App\Actions\Payment\Gateways\CashGatewayAction;
use App\Actions\Payment\Gateways\ChipGatewayAction;
use App\Actions\Payment\Gateways\StripeGatewayAction;
use App\Contracts\PaymentGatewayInterface;
use App\Enums\Gateway;

class PaymentGatewayFactory
{
    /**
     * Create a gateway action instance for the given gateway
     */
    public function make(Gateway $gateway): PaymentGatewayInterface
    {
        return match ($gateway) {
            Gateway::CHIP => app(ChipGatewayAction::class),
            Gateway::BANK_TRANSFER => app(BankTransferGatewayAction::class),
            Gateway::BILLPLZ => app(BillplzGatewayAction::class),
            Gateway::STRIPE => app(StripeGatewayAction::class),
            Gateway::CASH => app(CashGatewayAction::class),
        };
    }
}
