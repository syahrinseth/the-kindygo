<?php

namespace App\Actions\Auth;

use App\DataTransferObjects\AuthenticationResult;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the complete authentication flow.
 *
 * This action combines credential validation, email verification checks,
 * tenant access validation, and token generation into a single flow.
 */
class AuthenticateUserAction
{
    public function __construct(
        protected ValidateCredentialsAction $validateCredentials,
        protected GenerateTokenAction $generateToken
    ) {}

    /**
     * Execute the complete authentication flow.
     *
     * @param  string  $email  User's email address
     * @param  string  $password  User's password
     * @param  int|null  $tenantId  Specific tenant to authenticate for (null = default tenant)
     * @param  string|null  $deviceName  Name of the device for token identification
     * @param  bool  $requireEmailVerification  Whether to require email verification (default: true)
     */
    public function execute(
        string $email,
        string $password,
        ?int $tenantId = null,
        ?string $deviceName = null,
        bool $requireEmailVerification = true
    ): AuthenticationResult {
        // Step 1: Validate credentials
        $credentialResult = $this->validateCredentials->execute($email, $password);

        if (! $credentialResult['valid']) {
            Log::warning('Authentication failed - invalid credentials', [
                'email' => $email,
                'error_code' => $credentialResult['error_code'],
            ]);

            return AuthenticationResult::failure(
                message: 'The provided credentials are incorrect.',
                errorCode: 'invalid_credentials'
            );
        }

        /** @var User $user */
        $user = $credentialResult['user'];

        // Step 2: Check email verification (if required)
        if ($requireEmailVerification && ! $user->hasVerifiedEmail()) {
            Log::info('Authentication blocked - email not verified', [
                'user_id' => $user->id,
                'email' => $email,
            ]);

            return AuthenticationResult::emailNotVerified($user);
        }

        // Step 3: Determine tenant
        $tenant = $this->resolveTenant($user, $tenantId);

        if (! $tenant) {
            Log::warning('Authentication failed - no tenant access', [
                'user_id' => $user->id,
                'requested_tenant_id' => $tenantId,
            ]);

            return AuthenticationResult::noTenantAccess($user);
        }

        // Step 4: Update user's current tenant
        $user->update(['current_tenant_id' => $tenant->id]);
        $user->setCurrentTenant($tenant);

        // Step 5: Generate token
        $tokenResult = $this->generateToken->execute(
            user: $user,
            tenant: $tenant,
            deviceName: $deviceName
        );

        Log::info('Authentication successful', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'email' => $email,
        ]);

        return AuthenticationResult::success(
            user: $user,
            tenant: $tenant,
            tokenResult: $tokenResult,
            message: 'Authentication successful.'
        );
    }

    /**
     * Resolve which tenant to authenticate for.
     */
    protected function resolveTenant(User $user, ?int $tenantId): ?Tenant
    {
        // Load tenants relationship if not loaded
        if (! $user->relationLoaded('tenants')) {
            $user->load('tenants');
        }

        // If specific tenant requested, check access
        if ($tenantId !== null) {
            $tenant = $user->tenants->find($tenantId);

            return $tenant;
        }

        // Use current tenant if set and accessible
        if ($user->current_tenant_id) {
            $currentTenant = $user->tenants->find($user->current_tenant_id);
            if ($currentTenant) {
                return $currentTenant;
            }
        }

        // Fall back to default tenant
        return $user->getDefaultTenant();
    }
}
