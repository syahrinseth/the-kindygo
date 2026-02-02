<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revokes a specific Sanctum API token.
 */
class RevokeTokenAction
{
    /**
     * Execute the token revocation.
     *
     * @param  User  $user  The user who owns the token
     * @param  int  $tokenId  The ID of the token to revoke
     * @return bool Whether the token was successfully revoked
     */
    public function execute(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            Log::warning('Token revocation failed - token not found', [
                'user_id' => $user->id,
                'token_id' => $tokenId,
            ]);

            return false;
        }

        $token->delete();

        Log::info('API token revoked', [
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'token_name' => $token->name,
        ]);

        return true;
    }

    /**
     * Revoke a token by its plain text value (from Authorization header).
     *
     * @param  User  $user  The user who owns the token
     * @param  string  $plainTextToken  The plain text token value
     * @return bool Whether the token was successfully revoked
     */
    public function executeByPlainText(User $user, string $plainTextToken): bool
    {
        // Extract the token ID from the plain text token (format: id|token)
        $tokenId = explode('|', $plainTextToken, 2)[0] ?? null;

        if (! $tokenId || ! is_numeric($tokenId)) {
            // Try to find by hashing the token
            $token = PersonalAccessToken::findToken($plainTextToken);

            if (! $token || $token->tokenable_id !== $user->id) {
                Log::warning('Token revocation failed - invalid token', [
                    'user_id' => $user->id,
                ]);

                return false;
            }

            $token->delete();

            Log::info('API token revoked by plain text', [
                'user_id' => $user->id,
                'token_id' => $token->id,
            ]);

            return true;
        }

        return $this->execute($user, (int) $tokenId);
    }

    /**
     * Revoke the current token being used for this request.
     *
     * @param  User  $user  The authenticated user
     * @return bool Whether the token was successfully revoked
     */
    public function executeCurrentToken(User $user): bool
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            Log::warning('Token revocation failed - no current token or transient token', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        $tokenId = $token->id;
        $tokenName = $token->name;

        $token->delete();

        Log::info('Current API token revoked', [
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'token_name' => $tokenName,
        ]);

        return true;
    }
}
