<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;

/**
 * Data Transfer Object for token generation results.
 */
class TokenResult
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public Carbon $expiresAt,
        /** @var array<string> */
        public array $abilities,
        public ?int $deviceTokenId = null,
    ) {}

    /**
     * Create a new TokenResult instance.
     *
     * @param  array<string>  $abilities
     */
    public static function make(
        string $accessToken,
        Carbon $expiresAt,
        array $abilities,
        ?int $deviceTokenId = null
    ): self {
        return new self(
            accessToken: $accessToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt,
            abilities: $abilities,
            deviceTokenId: $deviceTokenId,
        );
    }

    /**
     * Get the token expiration in seconds from now.
     */
    public function expiresInSeconds(): int
    {
        return (int) now()->diffInSeconds($this->expiresAt, false);
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'expires_in' => $this->expiresInSeconds(),
            'abilities' => $this->abilities,
            'device_token_id' => $this->deviceTokenId,
        ];
    }
}
