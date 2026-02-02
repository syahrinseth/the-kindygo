<?php

namespace App\DataTransferObjects;

use App\Models\Tenant;
use App\Models\User;

/**
 * Data Transfer Object for registration results.
 */
class RegistrationResult
{
    public const VERIFICATION_CODE = 'code';

    public const VERIFICATION_LINK = 'link';

    public function __construct(
        public bool $success,
        public ?User $user,
        public ?Tenant $tenant,
        public ?TokenResult $tokenResult,
        public string $verificationMethod,
        public bool $requiresEmailVerification,
        public ?string $message = null,
        public ?string $errorCode = null,
    ) {}

    /**
     * Create a successful registration result.
     */
    public static function success(
        User $user,
        Tenant $tenant,
        TokenResult $tokenResult,
        string $verificationMethod = self::VERIFICATION_CODE,
        ?string $message = null
    ): self {
        return new self(
            success: true,
            user: $user,
            tenant: $tenant,
            tokenResult: $tokenResult,
            verificationMethod: $verificationMethod,
            requiresEmailVerification: ! $user->hasVerifiedEmail(),
            message: $message ?? 'Registration successful. Please verify your email.',
            errorCode: null,
        );
    }

    /**
     * Create a failed registration result.
     */
    public static function failure(
        string $message,
        string $errorCode = 'registration_failed',
        ?User $user = null
    ): self {
        return new self(
            success: false,
            user: $user,
            tenant: null,
            tokenResult: null,
            verificationMethod: self::VERIFICATION_CODE,
            requiresEmailVerification: false,
            message: $message,
            errorCode: $errorCode,
        );
    }

    /**
     * Create a result for existing user.
     */
    public static function userExists(?string $message = null): self
    {
        return new self(
            success: false,
            user: null,
            tenant: null,
            tokenResult: null,
            verificationMethod: self::VERIFICATION_CODE,
            requiresEmailVerification: false,
            message: $message ?? 'A user with this email already exists.',
            errorCode: 'user_exists',
        );
    }

    /**
     * Create a result for invalid tenant.
     */
    public static function invalidTenant(?string $message = null): self
    {
        return new self(
            success: false,
            user: null,
            tenant: null,
            tokenResult: null,
            verificationMethod: self::VERIFICATION_CODE,
            requiresEmailVerification: false,
            message: $message ?? 'The specified organisation does not exist or is not accepting registrations.',
            errorCode: 'invalid_tenant',
        );
    }

    /**
     * Check if registration was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if verification uses code method.
     */
    public function usesCodeVerification(): bool
    {
        return $this->verificationMethod === self::VERIFICATION_CODE;
    }

    /**
     * Check if verification uses link method.
     */
    public function usesLinkVerification(): bool
    {
        return $this->verificationMethod === self::VERIFICATION_LINK;
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
            'requires_email_verification' => $this->requiresEmailVerification,
            'verification_method' => $this->verificationMethod,
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
