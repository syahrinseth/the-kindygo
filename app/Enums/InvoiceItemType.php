<?php

namespace App\Enums;

enum InvoiceItemType: string
{
    case PRODUCT = 'product';
    case INVOICE_DISCOUNT = 'invoice_discount';

    /**
     * Get all enum values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the display name for the enum value.
     */
    public function label(): string
    {
        return match ($this) {
            self::PRODUCT => 'Product',
            self::INVOICE_DISCOUNT => 'Invoice Discount',
        };
    }

    /**
     * Get all enum cases with their labels.
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->toArray();
    }
}
