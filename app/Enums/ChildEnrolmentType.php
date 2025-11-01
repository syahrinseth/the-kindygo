<?php

namespace App\Enums;

enum ChildEnrolmentType: string
{
    case FULL_TIME = 'full_time';
    case PART_TIME = 'part_time';
    case TRIAL = 'trial';
    case SUMMER_PROGRAM = 'summer_program';
    case AFTER_SCHOOL = 'after_school';
    case HOLIDAY_PROGRAM = 'holiday_program';

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
        return collect(self::cases())->mapWithKeys(function ($type) {
            return [$type->value => ucwords(str_replace('_', ' ', $type->value))];
        })->all();
    }
}
