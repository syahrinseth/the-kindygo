<?php

namespace App\Constants;

/**
 * Token abilities (scopes) for Sanctum API tokens.
 *
 * These abilities define what actions a token holder can perform.
 * Abilities are assigned when generating tokens and checked via middleware.
 */
final class TokenAbility
{
    // Profile abilities
    public const PROFILE_READ = 'profile:read';

    public const PROFILE_WRITE = 'profile:write';

    // Children abilities
    public const CHILDREN_READ = 'children:read';

    // Invoice abilities
    public const INVOICES_READ = 'invoices:read';

    // Payment abilities
    public const PAYMENTS_READ = 'payments:read';

    public const PAYMENTS_CREATE = 'payments:create';

    // Notification abilities
    public const NOTIFICATIONS_READ = 'notifications:read';

    public const NOTIFICATIONS_WRITE = 'notifications:write';

    // Device token abilities
    public const DEVICE_TOKENS_READ = 'device-tokens:read';

    public const DEVICE_TOKENS_WRITE = 'device-tokens:write';

    // Tenant abilities
    public const TENANTS_READ = 'tenants:read';

    public const TENANTS_SWITCH = 'tenants:switch';

    /**
     * Get all abilities for a parent user.
     *
     * Parents have full access to their own data and children.
     *
     * @return array<string>
     */
    public static function parentAbilities(): array
    {
        return [
            self::PROFILE_READ,
            self::PROFILE_WRITE,
            self::CHILDREN_READ,
            self::INVOICES_READ,
            self::PAYMENTS_READ,
            self::PAYMENTS_CREATE,
            self::NOTIFICATIONS_READ,
            self::NOTIFICATIONS_WRITE,
            self::DEVICE_TOKENS_READ,
            self::DEVICE_TOKENS_WRITE,
            self::TENANTS_READ,
            self::TENANTS_SWITCH,
        ];
    }

    /**
     * Get read-only abilities (useful for limited access tokens).
     *
     * @return array<string>
     */
    public static function readOnlyAbilities(): array
    {
        return [
            self::PROFILE_READ,
            self::CHILDREN_READ,
            self::INVOICES_READ,
            self::PAYMENTS_READ,
            self::NOTIFICATIONS_READ,
            self::DEVICE_TOKENS_READ,
            self::TENANTS_READ,
        ];
    }

    /**
     * Get all available abilities.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::PROFILE_READ,
            self::PROFILE_WRITE,
            self::CHILDREN_READ,
            self::INVOICES_READ,
            self::PAYMENTS_READ,
            self::PAYMENTS_CREATE,
            self::NOTIFICATIONS_READ,
            self::NOTIFICATIONS_WRITE,
            self::DEVICE_TOKENS_READ,
            self::DEVICE_TOKENS_WRITE,
            self::TENANTS_READ,
            self::TENANTS_SWITCH,
        ];
    }

    /**
     * Get abilities grouped by resource.
     *
     * @return array<string, array<string>>
     */
    public static function grouped(): array
    {
        return [
            'profile' => [self::PROFILE_READ, self::PROFILE_WRITE],
            'children' => [self::CHILDREN_READ],
            'invoices' => [self::INVOICES_READ],
            'payments' => [self::PAYMENTS_READ, self::PAYMENTS_CREATE],
            'notifications' => [self::NOTIFICATIONS_READ, self::NOTIFICATIONS_WRITE],
            'device-tokens' => [self::DEVICE_TOKENS_READ, self::DEVICE_TOKENS_WRITE],
            'tenants' => [self::TENANTS_READ, self::TENANTS_SWITCH],
        ];
    }
}
