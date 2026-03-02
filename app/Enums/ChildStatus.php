<?php

namespace App\Enums;

enum ChildStatus: string
{
    case NEW = 'new';
    case FUTURE = 'future';
    case ACTIVE = 'active';
    case RETURN = 'return';
    case ALUMNI = 'alumni';
    case SUSPENDED = 'suspended';
    case REGISTERED = 'registered';
    case UNREGISTERED = 'unregistered';
    case TRIAL_1_MONTH = 'trial_1_month';
    case TRIAL_5_DAYS = 'trial_5_days';
    case INACTIVE = 'inactive';

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
