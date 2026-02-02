<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Validates user credentials without performing authentication.
 */
class ValidateCredentialsAction
{
    /**
     * Execute credential validation.
     *
     * @param  string  $email  User's email address
     * @param  string  $password  User's password
     * @return array{valid: bool, user: User|null, error_code: string|null}
     */
    public function execute(string $email, string $password): array
    {
        // Find user by email
        $user = User::where('email', $email)->first();

        if (! $user) {
            return [
                'valid' => false,
                'user' => null,
                'error_code' => 'user_not_found',
            ];
        }

        // Verify password
        if (! Hash::check($password, $user->password)) {
            return [
                'valid' => false,
                'user' => $user,
                'error_code' => 'invalid_password',
            ];
        }

        return [
            'valid' => true,
            'user' => $user,
            'error_code' => null,
        ];
    }

    /**
     * Check if a user exists by email.
     */
    public function userExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    /**
     * Find a user by email without password validation.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
