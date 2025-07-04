<?php

namespace App\Enums;

enum ProductPriority: int
{
    case CRITICAL = 4;
    case HIGH = 3;
    case MEDIUM = 2;
    case LOW = 1;

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
            self::CRITICAL => 'Critical',
            self::HIGH => 'High',
            self::MEDIUM => 'Medium',
            self::LOW => 'Low',
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
            self::CRITICAL => 'Highest priority items requiring immediate attention',
            self::HIGH => 'High priority items that should be addressed soon',
            self::MEDIUM => 'Medium priority items for normal processing',
            self::LOW => 'Low priority items that can be handled when time permits',
        };
    }

    /**
     * Get badge color for the enum value.
     *
     * @return string
     */
    public function getBadgeColor(): string
    {
        return match($this) {
            self::CRITICAL => 'danger',
            self::HIGH => 'warning',
            self::MEDIUM => 'info',
            self::LOW => 'success',
        };
    }

    /**
     * Get options array for forms.
     *
     * @return array
     */
    public static function getOptions(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [$case->value => $case->getDisplayName()])->toArray();
    }
}
