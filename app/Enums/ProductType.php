<?php

namespace App\Enums;

enum ProductType: string
{
    case SERVICE = 'service';
    case FEE = 'fee';
    case PRODUCT = 'product';
    case SUBSCRIPTION = 'subscription';
    case PROGRAMME = 'programme';
    case ANNUAL_FEE = 'annual_fee';

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
            self::SUBSCRIPTION => 'Subscription',
            self::PROGRAMME => 'Programme',
            self::ANNUAL_FEE => 'Annual Fee',
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
            self::SUBSCRIPTION => 'Recurring subscription services',
            self::PROGRAMME => 'Educational or structured programmes',
            self::ANNUAL_FEE => 'Yearly fees charged annually',
        };
    }

    /**
     * Get default payment priority for this product type.
     * Used in ledger allocation when payments are distributed across invoice items.
     */
    public function getDefaultPriority(): ProductPriority
    {
        return match ($this) {
            self::SUBSCRIPTION => ProductPriority::CRITICAL,  // Recurring tuition fees - highest priority
            self::ANNUAL_FEE => ProductPriority::CRITICAL,    // Annual registration - must be paid
            self::FEE => ProductPriority::HIGH,               // Admin/processing fees - important
            self::PROGRAMME => ProductPriority::MEDIUM,       // Special programmes - standard
            self::SERVICE => ProductPriority::MEDIUM,         // General services - standard
            self::PRODUCT => ProductPriority::LOW,            // Physical products - optional
        };
    }

    /**
     * Get payment allocation explanation for this product type.
     */
    public function getPriorityExplanation(): string
    {
        return match ($this) {
            self::SUBSCRIPTION => 'Recurring tuition fees are prioritised to ensure continuous enrolment',
            self::ANNUAL_FEE => 'Annual fees must be paid to maintain active registration',
            self::FEE => 'Administrative fees are important for processing services',
            self::PROGRAMME => 'Programme fees are allocated after critical fees',
            self::SERVICE => 'Service fees are allocated after critical fees',
            self::PRODUCT => 'Physical products are allocated last from available payment',
        };
    }
}
