<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Verifies email using a 6-digit code during mobile registration.
 *
 * This action handles the verification of email addresses during the
 * multi-step registration process. It uses cache-based storage for
 * verification codes with automatic expiration.
 */
class VerifyEmailForRegistrationAction
{
    /**
     * Cache key prefix for verification codes.
     */
    private const CACHE_PREFIX = 'registration_verification_code:';

    /**
     * Cache key prefix for temporary tokens.
     */
    private const TOKEN_PREFIX = 'registration_temp_token:';

    /**
     * Verification code expiry in minutes.
     */
    private const CODE_EXPIRY_MINUTES = 15;

    /**
     * Generate and store a verification code for a user.
     *
     * @param  User  $user  The user to generate the code for
     * @return array{code: string, temporary_token: string}
     */
    public function generateCode(User $user): array
    {
        // Generate a 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Generate a temporary token for the mobile app to use
        $temporaryToken = bin2hex(random_bytes(32));

        // Store the code in cache with user ID association
        Cache::put(
            self::CACHE_PREFIX.$user->id,
            [
                'code' => $code,
                'temporary_token' => $temporaryToken,
                'created_at' => now()->toIso8601String(),
            ],
            now()->addMinutes(self::CODE_EXPIRY_MINUTES)
        );

        // Also store the reverse lookup (token -> user_id)
        Cache::put(
            self::TOKEN_PREFIX.$temporaryToken,
            $user->id,
            now()->addMinutes(self::CODE_EXPIRY_MINUTES)
        );

        return [
            'code' => $code,
            'temporary_token' => $temporaryToken,
        ];
    }

    /**
     * Verify the code and mark email as verified.
     *
     * @param  string  $temporaryToken  The temporary token from step 1
     * @param  string  $code  The 6-digit verification code
     * @return array{success: bool, user: User|null, message: string, error_code: string|null}
     */
    public function verify(string $temporaryToken, string $code): array
    {
        // Look up user ID from temporary token
        $userId = Cache::get(self::TOKEN_PREFIX.$temporaryToken);

        if (! $userId) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'Invalid or expired verification session. Please restart registration.',
                'error_code' => 'invalid_token',
            ];
        }

        // Get the stored verification data
        $storedData = Cache::get(self::CACHE_PREFIX.$userId);

        if (! $storedData) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'Verification code has expired. Please request a new one.',
                'error_code' => 'code_expired',
            ];
        }

        // Verify the token matches
        if ($storedData['temporary_token'] !== $temporaryToken) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'Invalid verification session. Please restart registration.',
                'error_code' => 'token_mismatch',
            ];
        }

        // Verify the code matches
        if ($storedData['code'] !== $code) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'Invalid verification code. Please check and try again.',
                'error_code' => 'invalid_code',
            ];
        }

        // Find the user
        $user = User::find($userId);

        if (! $user) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'User not found. Please restart registration.',
                'error_code' => 'user_not_found',
            ];
        }

        // Mark email as verified
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        // Clear the verification data from cache
        Cache::forget(self::CACHE_PREFIX.$userId);
        Cache::forget(self::TOKEN_PREFIX.$temporaryToken);

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Email verified successfully.',
            'error_code' => null,
        ];
    }

    /**
     * Resend verification code for a user.
     *
     * @param  User  $user  The user to resend the code to
     * @return array{code: string, temporary_token: string}
     */
    public function resendCode(User $user): array
    {
        // Clear any existing codes
        $this->clearExistingCodes($user);

        // Generate a new code
        return $this->generateCode($user);
    }

    /**
     * Clear existing verification codes for a user.
     */
    protected function clearExistingCodes(User $user): void
    {
        $storedData = Cache::get(self::CACHE_PREFIX.$user->id);

        if ($storedData && isset($storedData['temporary_token'])) {
            Cache::forget(self::TOKEN_PREFIX.$storedData['temporary_token']);
        }

        Cache::forget(self::CACHE_PREFIX.$user->id);
    }

    /**
     * Check if a user has a valid pending verification.
     */
    public function hasPendingVerification(User $user): bool
    {
        return Cache::has(self::CACHE_PREFIX.$user->id);
    }

    /**
     * Get user ID from temporary token.
     */
    public function getUserIdFromToken(string $temporaryToken): ?int
    {
        return Cache::get(self::TOKEN_PREFIX.$temporaryToken);
    }
}
