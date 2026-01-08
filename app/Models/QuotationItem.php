<?php

namespace App\Models;

use App\Enums\InvoiceItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quotation_id',
        'product_id',
        'child_id',
        'child_enrolment_id',
        'name',
        'description',
        'price',
        'quantity',
        'discount',
        'total',
        'type',
        'paid_amount',
        'balance_amount',
        'paid',
        'effective_date',
        'period_start',
        'period_end',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'integer',
        'quantity' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
        'type' => InvoiceItemType::class,
        'paid_amount' => 'integer',
        'balance_amount' => 'integer',
        'paid' => 'boolean',
        'effective_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate total when creating or updating
        static::saving(function ($quotationItem) {
            $quotationItem->calculateTotal();
            $quotationItem->calculateBalance();
        });

        // Update quotation totals after creating an item
        static::created(function ($quotationItem) {
            if ($quotationItem->quotation_id) {
                $quotation = $quotationItem->quotation ?? Quotation::find($quotationItem->quotation_id);
                if ($quotation) {
                    $quotation->calculateAndUpdateTotals();
                }
            }
        });

        // Update quotation totals after updating an item
        static::updated(function ($quotationItem) {
            if ($quotationItem->quotation_id) {
                $quotation = $quotationItem->quotation ?? Quotation::find($quotationItem->quotation_id);
                if ($quotation) {
                    $quotation->calculateAndUpdateTotals();
                }
            }
        });

        // Update quotation totals after deleting an item
        static::deleted(function ($quotationItem) {
            if ($quotationItem->quotation_id) {
                $quotation = $quotationItem->quotation ?? Quotation::find($quotationItem->quotation_id);
                if ($quotation) {
                    $quotation->calculateAndUpdateTotals();
                }
            }
        });
    }

    /**
     * Get the quotation that owns the quotation item.
     *
     * @return BelongsTo
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Get the product associated with the quotation item.
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the child associated with the quotation item.
     *
     * @return BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the child enrolment associated with the quotation item.
     *
     * @return BelongsTo
     */
    public function childEnrolment(): BelongsTo
    {
        return $this->belongsTo(ChildEnrolment::class);
    }

    /**
     * Calculate and set the total amount.
     * Discount is applied per unit and multiplied by quantity.
     *
     * @return void
     */
    public function calculateTotal(): void
    {
        $subtotal = $this->price * $this->quantity;
        $totalDiscount = $this->discount * $this->quantity;
        $this->total = $subtotal - $totalDiscount;
    }

    /**
     * Calculate and set the balance amount.
     *
     * @return void
     */
    public function calculateBalance(): void
    {
        $this->balance_amount = $this->total - $this->paid_amount;
        $this->paid = $this->balance_amount <= 0;
    }

    /**
     * Get the payment status.
     *
     * @return string
     */
    public function getPaymentStatus(): string
    {
        if ($this->paid) {
            return 'Paid';
        }

        if ($this->paid_amount > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid';
    }

    /**
     * Format the price as a decimal.
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price / 100, 2);
    }

    /**
     * Format the discount as a decimal.
     *
     * @return string
     */
    public function getFormattedDiscount(): string
    {
        return number_format($this->discount / 100, 2);
    }

    /**
     * Format the total as a decimal.
     *
     * @return string
     */
    public function getFormattedTotal(): string
    {
        return number_format($this->total / 100, 2);
    }

    /**
     * Format the paid amount as a decimal.
     *
     * @return string
     */
    public function getFormattedPaidAmount(): string
    {
        return number_format($this->paid_amount / 100, 2);
    }

    /**
     * Format the balance amount as a decimal.
     *
     * @return string
     */
    public function getFormattedBalanceAmount(): string
    {
        return number_format($this->balance_amount / 100, 2);
    }

    /**
     * Calculate discount percentage per unit.
     *
     * @return float
     */
    public function getDiscountPercentage(): float
    {
        if ($this->price === 0) {
            return 0;
        }

        return ($this->discount / $this->price) * 100;
    }

    /**
     * Scope a query to filter by quotation.
     *
     * @param Builder $query
     * @param int $quotationId
     * @return Builder
     */
    public function scopeForQuotation(Builder $query, int $quotationId): Builder
    {
        return $query->where('quotation_id', $quotationId);
    }

    /**
     * Scope a query to filter by child.
     *
     * @param Builder $query
     * @param int $childId
     * @return Builder
     */
    public function scopeForChild(Builder $query, int $childId): Builder
    {
        return $query->where('child_id', $childId);
    }

    /**
     * Scope a query to filter items with discounts.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithDiscount(Builder $query): Builder
    {
        return $query->where('discount', '>', 0);
    }

    /**
     * Scope a query to filter paid items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    /**
     * Scope a query to filter unpaid items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }
}
