<?php

namespace App\Models;

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

    /**
     * Get the user that owns the address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if address information is complete for e-invoice.
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return !empty($this->address) && 
               !empty($this->city) && 
               !empty($this->postal_code) && 
               !empty($this->state_code);
    }
    
    /**
     * Get formatted address for e-invoice display.
     *
     * @return string
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
     *
     * @return string
     */
    public function getStateName(): string
    {
        $stateMapping = [
            '01' => 'Johor',
            '02' => 'Kedah',
            '03' => 'Kelantan',
            '04' => 'Melaka',
            '05' => 'Negeri Sembilan',
            '06' => 'Pahang',
            '07' => 'Pulau Pinang',
            '08' => 'Perak',
            '09' => 'Perlis',
            '10' => 'Selangor',
            '11' => 'Terengganu',
            '12' => 'Sabah',
            '13' => 'Sarawak',
            '14' => 'Wilayah Persekutuan Kuala Lumpur',
            '15' => 'Wilayah Persekutuan Labuan',
            '16' => 'Wilayah Persekutuan Putrajaya',
        ];
        
        return $stateMapping[$this->state_code] ?? $this->state_code ?? '';
    }
}
