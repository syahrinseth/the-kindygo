<?php

namespace App\Enums;

enum ProductType: string
{
    case SERVICE = 'service';
    case FEE = 'fee';
    case PRODUCT = 'product';
    case PROGRAMME = 'programme';
    case ANNUAL_FEE = 'annual_fee';
    case OTHERS = 'others';
    case EVENT = 'event';
    case MERCHANDISE = 'merchandise';
    case OVERTIME = 'overtime';
    case STAYIN = 'stayin';
    case DEPOSIT = 'deposit';

    /**
     * Get all values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get display name for the enum value.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::SERVICE => 'Service',
            self::FEE => 'Fee',
            self::PRODUCT => 'Product',
            self::PROGRAMME => 'Programme',
            self::ANNUAL_FEE => 'Annual Fee',
            self::OTHERS => 'Others',
            self::EVENT => 'Event',
            self::MERCHANDISE => 'Merchandise',
            self::OVERTIME => 'Overtime',
            self::STAYIN => 'Stay In',
            self::DEPOSIT => 'Deposit',
        };
    }

    /**
     * Get description for the enum value.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SERVICE => 'General service offerings',
            self::FEE => 'Administrative or processing fees',
            self::PRODUCT => 'Physical or digital products',
            self::PROGRAMME => 'Educational or structured programmes',
            self::ANNUAL_FEE => 'Yearly fees charged annually',
            self::OTHERS => 'Miscellaneous products and services',
            self::EVENT => 'Special events and activities',
            self::MERCHANDISE => 'Physical merchandise and materials',
            self::OVERTIME => 'Extended hours or overtime charges',
            self::STAYIN => 'Stay-in or boarding charges',
            self::DEPOSIT => 'Refundable or non-refundable deposits',
        };
    }

    /**
     * Get default payment priority for this product type.
     * Used in ledger allocation when payments are distributed across invoice items.
     */
    public function getDefaultPriority(): ProductPriority
    {
        return match ($this) {
            self::ANNUAL_FEE => ProductPriority::CRITICAL,    // Annual registration - must be paid
            self::FEE => ProductPriority::HIGH,               // Admin/processing fees - important
            self::DEPOSIT => ProductPriority::HIGH,            // Deposits - important upfront
            self::PROGRAMME => ProductPriority::MEDIUM,       // Special programmes - standard
            self::SERVICE => ProductPriority::MEDIUM,         // General services - standard
            self::EVENT => ProductPriority::MEDIUM,            // Events - standard
            self::PRODUCT => ProductPriority::LOW,            // Physical products - optional
            self::MERCHANDISE => ProductPriority::LOW,         // Merchandise - optional
            self::OVERTIME => ProductPriority::LOW,            // Overtime - optional
            self::STAYIN => ProductPriority::LOW,              // Stay in - optional
            self::OTHERS => ProductPriority::LOW,             // Miscellaneous items - optional
        };
    }

    /**
     * Get the Filament badge colour for the product type.
     */
    public function getBadgeColor(): string
    {
        return match ($this) {
            self::SERVICE => 'primary',
            self::PRODUCT => 'secondary',
            self::FEE => 'info',
            self::PROGRAMME => 'success',
            self::ANNUAL_FEE => 'danger',
            self::OTHERS => 'gray',
            self::EVENT => 'info',
            self::MERCHANDISE => 'secondary',
            self::OVERTIME => 'warning',
            self::STAYIN => 'primary',
            self::DEPOSIT => 'danger',
        };
    }

    /**
     * Get payment allocation explanation for this product type.
     */
    public function getPriorityExplanation(): string
    {
        return match ($this) {
            self::ANNUAL_FEE => 'Annual fees must be paid to maintain active registration',
            self::FEE => 'Administrative fees are important for processing services',
            self::DEPOSIT => 'Deposits are required upfront before enrolment',
            self::PROGRAMME => 'Programme fees are allocated after critical fees',
            self::SERVICE => 'Service fees are allocated after critical fees',
            self::EVENT => 'Event fees are allocated after critical fees',
            self::PRODUCT => 'Physical products are allocated last from available payment',
            self::MERCHANDISE => 'Merchandise is allocated from remaining payment',
            self::OVERTIME => 'Overtime charges are allocated from remaining payment',
            self::STAYIN => 'Stay-in charges are allocated from remaining payment',
            self::OTHERS => 'Miscellaneous items are allocated from remaining payment',
        };
    }
}
