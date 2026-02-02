<?php

namespace App\DataTransferObjects;

use App\Models\Tenant;
use App\Models\User;

/**
 * Data Transfer Object for authentication results.
 */
class AuthenticationResult
{
    public function __construct(
        public bool $success,
        public ?User $user,
        public ?Tenant $tenant,
        public ?TokenResult $tokenResult,
        public bool $emailVerified,
        public ?string $message = null,
        public ?string $errorCode = null,
    ) {}

    /**
     * Create a successful authentication result.
     */
    public static function success(
        User $user,
        Tenant $tenant,
        TokenResult $tokenResult,
        ?string $message = null
    ): self {
        return new self(
            success: true,
            user: $user,
            tenant: $tenant,
            tokenResult: $tokenResult,
            emailVerified: $user->hasVerifiedEmail(),
            message: $message,
            errorCode: null,
        );
    }

    /**
     * Create a failed authentication result.
     */
    public static function failure(
        string $message,
        string $errorCode = 'authentication_failed',
        ?User $user = null
    ): self {
        return new self(
            success: false,
            user: $user,
            tenant: null,
            tokenResult: null,
            emailVerified: $user?->hasVerifiedEmail() ?? false,
            message: $message,
            errorCode: $errorCode,
        );
    }

    /**
     * Create a result for unverified email.
     */
    public static function emailNotVerified(User $user, ?string $message = null): self
    {
        return new self(
            success: false,
            user: $user,
            tenant: null,
            tokenResult: null,
            emailVerified: false,
            message: $message ?? 'Please verify your email address before logging in.',
            errorCode: 'email_not_verified',
        );
    }

    /**
     * Create a result for user with no tenant access.
     */
    public static function noTenantAccess(User $user, ?string $message = null): self
    {
        return new self(
            success: false,
            user: $user,
            tenant: null,
            tokenResult: null,
            emailVerified: $user->hasVerifiedEmail(),
            message: $message ?? 'You do not have access to any organisation.',
            errorCode: 'no_tenant_access',
        );
    }

    /**
     * Check if authentication was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if email verification is required.
     */
    public function requiresEmailVerification(): bool
    {
        return ! $this->emailVerified && $this->errorCode === 'email_not_verified';
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'message' => $this->message,
            'email_verified' => $this->emailVerified,
        ];

        if ($this->errorCode) {
            $data['error_code'] = $this->errorCode;
        }

        if ($this->success && $this->tokenResult) {
            $data['token'] = $this->tokenResult->toArray();
        }

        return $data;
    }
}
