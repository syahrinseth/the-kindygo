<?php

namespace App\Enums;

enum Gateway: string
{
    case BANK_TRANSFER = 'bank_transfer'; // Duit Now/IBG or CDM or Zakat or JKM or Cheque or Baitumal or ANIS
    case CHIP = 'chip';
    case BILLPLZ = 'billplz';
    case STRIPE = 'stripe';
    case CASH = 'cash';
}
