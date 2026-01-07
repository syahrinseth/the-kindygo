<?php

namespace App\Models;

use App\Enums\MalaysianState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'address',
        'address_2',
        'city',
        'postal_code',
        'state_code',
    ];

    protected $casts = [
        'state_code' => MalaysianState::class,
    ];

    /**
     * Get the user that owns the address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if address information is complete for e-invoice.
     */
    public function isComplete(): bool
    {
        return ! empty($this->address) &&
               ! empty($this->city) &&
               ! empty($this->postal_code) &&
               ! empty($this->state_code);
    }

    /**
     * Get formatted address for e-invoice display.
     */
    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->address_2,
            $this->city,
            $this->postal_code,
            $this->getStateName(),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get state name from state code.
     */
    public function getStateName(): string
    {
        return MalaysianState::getNameFromCode($this->state_code);
    }
}
