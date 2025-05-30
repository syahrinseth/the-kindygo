<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'centre_id',
        'user_id',
        'gateway',
        'reference_no',
        'status',
        'amount',
        'description',
        'paid_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Get the invoices associated with the payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * Format a monetary amount for display.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedAmount(bool $includeCurrency = true): string
    {
        $formatted = number_format($this->amount / 100, 2);
        return $includeCurrency ? 'RM' . $formatted : $formatted;
    }
}