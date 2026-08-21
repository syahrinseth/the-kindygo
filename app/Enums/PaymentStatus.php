<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_PAID = 'partially_paid';
    case UNPAID = 'unpaid';

    /**
     * Get all values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable name for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::UNPAID => 'Unpaid',
        };
    }

    /**
     * Get the Filament colour used to present the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING, self::PARTIALLY_PAID => 'warning',
            self::PAID => 'success',
            self::FAILED => 'danger',
            self::CANCELLED, self::UNPAID => 'gray',
            self::REFUNDED => 'info',
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
