<?php

namespace App\Actions\Auth;

use App\DataTransferObjects\TokenResult;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Switches the user's current tenant context and generates a new token.
 */
class SwitchTenantAction
{
    public function __construct(
        protected GenerateTokenAction $generateToken,
        protected RevokeTokenAction $revokeToken
    ) {}

    /**
     * Execute tenant switch.
     *
     * @param  User  $user  The authenticated user
     * @param  Tenant  $tenant  The tenant to switch to
     * @param  string|null  $deviceName  Device name for the new token
     * @param  bool  $revokeCurrentToken  Whether to revoke the current token
     * @return array{success: bool, token_result: TokenResult|null, message: string}
     */
    public function execute(
        User $user,
        Tenant $tenant,
        ?string $deviceName = null,
        bool $revokeCurrentToken = true
    ): array {
        // Check if user has access to the tenant
        if (! $user->canAccessTenant($tenant)) {
            Log::warning('Tenant switch denied - no access', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            return [
                'success' => false,
                'token_result' => null,
                'message' => 'You do not have access to this organisation.',
            ];
        }

        // Revoke current token if requested
        if ($revokeCurrentToken) {
            $this->revokeToken->executeCurrentToken($user);
        }

        // Update user's current tenant
        $user->update(['current_tenant_id' => $tenant->id]);
        $user->setCurrentTenant($tenant);

        // Generate new token for the tenant context
        $tokenResult = $this->generateToken->execute(
            user: $user,
            tenant: $tenant,
            deviceName: $deviceName
        );

        Log::info('Tenant switch successful', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        return [
            'success' => true,
            'token_result' => $tokenResult,
            'message' => 'Successfully switched to '.$tenant->name,
        ];
    }

    /**
     * Switch to tenant by ID.
     *
     * @param  User  $user  The authenticated user
     * @param  int  $tenantId  The tenant ID to switch to
     * @param  string|null  $deviceName  Device name for the new token
     * @return array{success: bool, token_result: TokenResult|null, message: string}
     */
    public function executeById(
        User $user,
        int $tenantId,
        ?string $deviceName = null
    ): array {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return [
                'success' => false,
                'token_result' => null,
                'message' => 'Organisation not found.',
            ];
        }

        return $this->execute($user, $tenant, $deviceName);
    }
}
