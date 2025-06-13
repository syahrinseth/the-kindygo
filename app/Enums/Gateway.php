<?php

namespace App\Enums;

enum Gateway: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CHIP = 'chip';
    case CASH = 'cash';
}
