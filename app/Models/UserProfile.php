<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'nric',
        'passport',
        'phone',
        'occupation',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the appropriate identification scheme for e-invoice.
     * Returns 'TIN', 'NRIC', or 'PASSPORT' based on available data.
     *
     * @return string
     */
    public function getEInvoiceSchemeId(): string
    {
        // For Malaysian individuals, use NRIC if available
        if (!empty($this->nric)) {
            return 'NRIC';
        }
        
        // For foreign customers, use passport
        if (!empty($this->passport)) {
            return 'PASSPORT';
        }
        
        // Default to NRIC for Malaysian customers
        return 'NRIC';
    }
    
    /**
     * Get the identification value for e-invoice.
     *
     * @return string
     * @throws \Exception if no valid identification is available
     */
    public function getEInvoiceIdentification(): string
    {
        // Priority: NRIC > Passport
        if (!empty($this->nric)) {
            return $this->nric;
        }
        
        if (!empty($this->passport)) {
            return $this->passport;
        }
        
        // If no identification available, throw exception
        throw new \Exception("Customer '{$this->user->name}' must have a valid NRIC or Passport number for e-Invoice submission.");
    }
}
