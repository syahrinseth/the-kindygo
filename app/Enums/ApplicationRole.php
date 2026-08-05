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
