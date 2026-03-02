<?php

namespace App\Enums;

enum ChildEnrolmentStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case LEGACY_FUTURE_RETURN = 'legacy_future_return';
    case LEGACY_SUSPENDED = 'legacy_suspended';
    case LEGACY_REGISTERED = 'legacy_registered';
    case LEGACY_UNREGISTERED = 'legacy_unregistered';
    case LEGACY_TRAIL_1_MONTH = 'legacy_trial_1_month';
    case LEGACY_TRAIL_5_DAYS = 'legacy_trial_5_days';

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
     * Get all cases as an array for select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($status) {
            return [$status->value => ucfirst($status->value)];
        })->all();
    }
}
