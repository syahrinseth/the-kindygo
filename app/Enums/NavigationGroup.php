<?php

namespace App\Enums;

enum NavigationGroup: string
{
    case FINANCE = 'Finance';
    case CAMPUS_MANAGEMENT = 'Campus Management';
    case CHILD_MANAGEMENT = 'Child Management';
    case FINANCIAL_MANAGEMENT = 'Financial Management';
    case USER_MANAGEMENT = 'User Management';
    case INVENTORY = 'Inventory';

    /**
     * Get all values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
