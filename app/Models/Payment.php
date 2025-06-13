<?php

namespace App\Models;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Payment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

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
        'gateway_payment_id',
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
        'gateway' => Gateway::class,
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
     * Get the user who made the payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * Register media collections for the payment model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('payment_proof')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
            ->useDisk('private')
            ->singleFile();
    }

    /**
     * Register media conversions for the payment model.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
              ->width(300)
              ->height(300)
              ->sharpen(10)
              ->performOnCollections('payment_proof')
              ->nonQueued();
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}