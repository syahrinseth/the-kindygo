<?php

namespace App\Models;

use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'device_token',
        'device_name',
        'device_type',
        'push_token_verified_at',
        'last_used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push_token_verified_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this device token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant associated with this device token.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Mark the device token as verified.
     */
    public function markVerified(): void
    {
        $this->update(['push_token_verified_at' => now()]);
    }

    /**
     * Check if the token is verified.
     */
    public function isVerified(): bool
    {
        return $this->push_token_verified_at !== null;
    }

    /**
     * Update the last used timestamp.
     */
    public function touchLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Check if the token was used recently (within 30 days).
     */
    public function isActive(): bool
    {
        if ($this->last_used_at === null) {
            return $this->created_at->isAfter(now()->subDays(30));
        }

        return $this->last_used_at->isAfter(now()->subDays(30));
    }
}
