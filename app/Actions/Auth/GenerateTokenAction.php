<?php

namespace App\Actions\Auth;

use App\Constants\TokenAbility;
use App\DataTransferObjects\TokenResult;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Generates a new Sanctum API token for a user.
 */
class GenerateTokenAction
{
    /**
     * Execute the token generation.
     *
     * @param  User  $user  The user to generate a token for
     * @param  Tenant  $tenant  The tenant context for the token
     * @param  array<string>|null  $abilities  Token abilities (null = parent abilities)
     * @param  string|null  $deviceName  Name of the device (for token identification)
     * @param  int|null  $expirationMinutes  Token expiration in minutes (null = config default)
     */
    public function execute(
        User $user,
        Tenant $tenant,
        ?array $abilities = null,
        ?string $deviceName = null,
        ?int $expirationMinutes = null
    ): TokenResult {
        // Use parent abilities by default
        $abilities = $abilities ?? TokenAbility::parentAbilities();

        // Use config expiration by default (30 days = 43200 minutes)
        $expirationMinutes = $expirationMinutes ?? config('sanctum.expiration', 43200);

        // Calculate expiration time
        $expiresAt = Carbon::now()->addMinutes($expirationMinutes);

        // Build token name with tenant context
        $tokenName = $this->buildTokenName($tenant, $deviceName);

        // Create the token with expiration
        $token = $user->createToken(
            name: $tokenName,
            abilities: $abilities,
            expiresAt: $expiresAt
        );

        Log::info('API token generated', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'token_name' => $tokenName,
            'abilities_count' => count($abilities),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return TokenResult::make(
            accessToken: $token->plainTextToken,
            expiresAt: $expiresAt,
            abilities: $abilities,
        );
    }

    /**
     * Build a descriptive token name including tenant context.
     */
    protected function buildTokenName(Tenant $tenant, ?string $deviceName): string
    {
        $parts = [
            'tenant:'.$tenant->id,
        ];

        if ($deviceName) {
            $parts[] = $deviceName;
        } else {
            $parts[] = 'mobile-app';
        }

        $parts[] = now()->format('Y-m-d_H:i:s');

        return implode('|', $parts);
    }
}
