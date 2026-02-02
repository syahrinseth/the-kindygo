<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Retrieves active (non-expired) tokens for a user.
 */
class GetActiveTokensAction
{
    /**
     * Execute to get active tokens.
     *
     * @param  User  $user  The user to get tokens for
     * @param  int|null  $tenantId  Filter by tenant ID (null = all tenants)
     * @return Collection Active tokens
     */
    public function execute(User $user, ?int $tenantId = null): Collection
    {
        $query = $user->tokens()
            ->where(function ($q) {
                // Token is not expired (expires_at is null or in the future)
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('last_used_at', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by tenant if specified
        if ($tenantId !== null) {
            $query->where('name', 'like', "tenant:{$tenantId}|%");
        }

        return $query->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $this->parseTokenName($token->name),
                'device_name' => $this->extractDeviceName($token->name),
                'tenant_id' => $this->extractTenantId($token->name),
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
                'is_current' => false, // Will be set by caller if needed
            ];
        });
    }

    /**
     * Parse the token name to extract components.
     *
     * @return array<string, mixed>
     */
    protected function parseTokenName(string $name): array
    {
        $parts = explode('|', $name);

        return [
            'raw' => $name,
            'parts' => $parts,
        ];
    }

    /**
     * Extract the device name from the token name.
     */
    protected function extractDeviceName(string $name): ?string
    {
        $parts = explode('|', $name);

        // Format: tenant:id|device_name|timestamp
        return $parts[1] ?? null;
    }

    /**
     * Extract the tenant ID from the token name.
     */
    protected function extractTenantId(string $name): ?int
    {
        $parts = explode('|', $name);

        // Format: tenant:id|device_name|timestamp
        if (isset($parts[0]) && str_starts_with($parts[0], 'tenant:')) {
            $tenantId = str_replace('tenant:', '', $parts[0]);

            return is_numeric($tenantId) ? (int) $tenantId : null;
        }

        return null;
    }

    /**
     * Count active tokens for a user.
     */
    public function count(User $user, ?int $tenantId = null): int
    {
        $query = $user->tokens()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        if ($tenantId !== null) {
            $query->where('name', 'like', "tenant:{$tenantId}|%");
        }

        return $query->count();
    }
}
