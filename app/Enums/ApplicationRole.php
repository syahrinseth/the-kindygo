<?php

namespace App\Enums;

enum ApplicationRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Principal = 'principal';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case Staff = 'staff';
    case Auditor = 'auditor';
    case Owner = 'owner';

    public static function normalise(string $role): string
    {
        foreach (self::cases() as $applicationRole) {
            if ($role === $applicationRole->value || $role === $applicationRole->label()) {
                return $applicationRole->value;
            }
        }

        return $role;
    }

    /**
     * Get the selectable role values and their display labels.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }

    public static function labelFor(?string $role): string
    {
        if ($role === null || $role === '') {
            return '';
        }

        $canonicalRole = self::normalise($role);

        return self::tryFrom($canonicalRole)?->label() ?? str($canonicalRole)->headline();
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Accountant => 'Accountant',
            self::Principal => 'Principal',
            self::Teacher => 'Teacher',
            self::Parent => 'Parent',
            self::Staff => 'Staff',
            self::Auditor => 'Auditor',
            self::Owner => 'Owner',
        };
    }
}
