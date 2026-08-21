<?php

namespace App\Enums;

enum Gateway: string
{
    case BANK_TRANSFER = 'bank_transfer'; // Duit Now/IBG or CDM or Zakat or JKM or Cheque or Baitumal or ANIS
    case CHIP = 'chip';
    case BILLPLZ = 'billplz';
    case STRIPE = 'stripe';
    case CASH = 'cash';

    /**
     * Get the human-readable payment method name.
     */
    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank transfer',
            self::CHIP => 'CHIP',
            self::BILLPLZ => 'Billplz',
            self::STRIPE => 'Stripe',
            self::CASH => 'Cash',
        };
    }
}
