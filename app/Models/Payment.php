<?php

namespace App\Models;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
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
        'user_id',
        'gateway',
        'reference_no',
        'gateway_payment_id',
        'gateway_payment_data',
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
        'gateway_payment_data' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Get the invoices associated with the payment.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * Get the centres associated with this payment.
     */
    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class, 'payment_centre')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    /**
     * Get the user who made the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant that owns the payment.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the amount allocated to a specific centre (in cents).
     */
    public function getCentreAllocation(int $centreId): int
    {
        return $this->centres()
            ->where('centre_id', $centreId)
            ->first()
            ?->pivot
            ?->allocated_amount ?? 0;
    }

    /**
     * Check if this payment covers invoices from multiple centres.
     */
    public function isMultiCentre(): bool
    {
        return $this->centres()->count() > 1;
    }

    /**
     * Get the primary centre (first centre, for display purposes).
     */
    public function getPrimaryCentre(): ?Centre
    {
        return $this->centres()->first();
    }

    /**
     * Format a monetary amount for display.
     *
     * @param  bool  $includeCurrency  Whether to include the currency symbol
     */
    public function getFormattedAmount(bool $includeCurrency = true): string
    {
        $formatted = number_format($this->amount / 100, 2);

        return $includeCurrency ? 'RM'.$formatted : $formatted;
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
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('payment_proof')
            ->nonQueued();
    }

    /**
     * Get CHIP payment data if this is a CHIP payment
     */
    public function getChipData(): ?array
    {
        if ($this->gateway !== Gateway::CHIP) {
            return null;
        }

        return $this->gateway_payment_data;
    }

    /**
     * Get nested CHIP data from gateway_payment_data['chip_data']
     */
    public function getNestedChipData(): ?array
    {
        if ($this->gateway !== Gateway::CHIP) {
            return null;
        }

        $gatewayData = $this->gateway_payment_data;
        if (! is_array($gatewayData)) {
            return null;
        }

        return $gatewayData['chip_data'] ?? null;
    }

    /**
     * Get specific CHIP payment information with chip_data fallback
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getChipInfo(string $key, $default = null)
    {
        $gatewayData = $this->getChipData();

        if (! $gatewayData || ! is_array($gatewayData)) {
            return $default;
        }

        // Try nested chip_data first, then fallback to root level
        $nestedValue = data_get($gatewayData, "chip_data.{$key}");
        if ($nestedValue !== null) {
            return $nestedValue;
        }

        // Fallback to root level for legacy data
        return data_get($gatewayData, $key, $default);
    }

    /**
     * Check if this is a CHIP payment
     */
    public function isChipPayment(): bool
    {
        return $this->gateway === Gateway::CHIP;
    }

    /**
     * Get CHIP payment status from gateway data
     */
    public function getChipStatus(): ?string
    {
        return $this->getChipInfo('status');
    }

    /**
     * Get CHIP payment method from gateway data
     */
    public function getChipPaymentMethod(): ?string
    {
        return $this->getChipInfo('payment_method');
    }

    /**
     * Get CHIP client email from gateway data
     */
    public function getChipClientEmail(): ?string
    {
        return $this->getChipInfo('client_email');
    }

    /**
     * Get CHIP transaction ID from gateway data
     */
    public function getChipTransactionId(): ?string
    {
        $transactionId = $this->getChipInfo('transaction_id');
        if ($transactionId) {
            return $transactionId;
        }

        // Try fpx_transaction_id as fallback
        return $this->getChipInfo('fpx_transaction_id');
    }

    /**
     * Get CHIP bank name from gateway data
     */
    public function getChipBankName(): ?string
    {
        return $this->getChipInfo('bank_name');
    }

    /**
     * Get CHIP reference from gateway data
     */
    public function getChipReference(): ?string
    {
        return $this->getChipInfo('reference');
    }
}
