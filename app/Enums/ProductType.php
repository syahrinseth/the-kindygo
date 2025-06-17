<?php

namespace App\Enums;

enum ProductType: string
{
    case SERVICE = 'service';
    case FEE = 'fee';
    case PRODUCT = 'product';
    case SUBSCRIPTION = 'subscription';

    /**
     * Get all values as an array.
     *
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get display name for the enum value.
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::SERVICE => 'Service',
            self::FEE => 'Fee',
            self::PRODUCT => 'Product',
            self::SUBSCRIPTION => 'Subscription',
        };
    }

    /**
     * Get description for the enum value.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::SERVICE => 'General service offerings',
            self::FEE => 'Administrative or processing fees',
            self::PRODUCT => 'Physical or digital products',
            self::SUBSCRIPTION => 'Recurring subscription services',
        };
    }
}
