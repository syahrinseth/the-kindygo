<?php

namespace App\Enums;

enum ChildEnrolmentBilledEvery: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';
    case ONE_TIME = 'one_time';

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
        return collect(self::cases())->mapWithKeys(function ($billedEvery) {
            return [$billedEvery->value => ucwords(str_replace('_', ' ', $billedEvery->value))];
        })->all();
    }
}
