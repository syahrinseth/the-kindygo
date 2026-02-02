<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Revokes all Sanctum API tokens for a user.
 */
class RevokeAllTokensAction
{
    /**
     * Execute the revocation of all tokens.
     *
     * @param  User  $user  The user whose tokens should be revoked
     * @param  bool  $exceptCurrent  Whether to keep the current token (default: false)
     * @param  int|null  $tenantId  Only revoke tokens for a specific tenant (null = all tenants)
     * @return int Number of tokens revoked
     */
    public function execute(
        User $user,
        bool $exceptCurrent = false,
        ?int $tenantId = null
    ): int {
        $query = $user->tokens();

        // Exclude current token if requested
        if ($exceptCurrent) {
            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $query->where('id', '!=', $currentToken->id);
            }
        }

        // Filter by tenant if specified
        if ($tenantId !== null) {
            $query->where('name', 'like', "tenant:{$tenantId}|%");
        }

        $count = $query->count();
        $query->delete();

        Log::info('All API tokens revoked', [
            'user_id' => $user->id,
            'tokens_revoked' => $count,
            'except_current' => $exceptCurrent,
            'tenant_id' => $tenantId,
        ]);

        return $count;
    }

    /**
     * Revoke all tokens except the current one.
     *
     * @param  User  $user  The user whose tokens should be revoked
     * @return int Number of tokens revoked
     */
    public function executeExceptCurrent(User $user): int
    {
        return $this->execute($user, exceptCurrent: true);
    }

    /**
     * Revoke all tokens for a specific tenant.
     *
     * @param  User  $user  The user whose tokens should be revoked
     * @param  int  $tenantId  The tenant ID to revoke tokens for
     * @return int Number of tokens revoked
     */
    public function executeForTenant(User $user, int $tenantId): int
    {
        return $this->execute($user, exceptCurrent: false, tenantId: $tenantId);
    }
}
