<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOfficeInfo extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'office_phone',
        'office_address',
        'office_address_2',
        'office_city',
        'office_postal_code',
        'office_state_code',
    ];

    /**
     * Get the user that owns the office info.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if office address information is complete.
     *
     * @return bool
     */
    public function hasCompleteAddress(): bool
    {
        return !empty($this->office_address) && 
               !empty($this->office_city) && 
               !empty($this->office_postal_code) && 
               !empty($this->office_state_code);
    }
    
    /**
     * Get formatted office address.
     *
     * @return string
     */
    public function getFormattedAddress(): string
    {
        $parts = array_filter([
            $this->office_address,
            $this->office_address_2,
            $this->office_city,
            $this->office_postal_code,
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
        
        return $stateMapping[$this->office_state_code] ?? $this->office_state_code ?? '';
    }
}
