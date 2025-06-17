<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'product_id',
        'child_id',
        'name',
        'price',
        'quantity',
        'discount',
        'total',
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
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate total when creating or updating
        static::saving(function ($invoiceItem) {
            $invoiceItem->calculateTotal();
        });
        
        // Update invoice totals after creating an item
        static::created(function ($invoiceItem) {
            if ($invoiceItem->invoice_id) {
                $invoice = $invoiceItem->invoice ?? Invoice::find($invoiceItem->invoice_id);
                if ($invoice) {
                    $invoice->calculateAndUpdateTotals();
                }
            }
        });
        
        // Update invoice totals after updating an item
        static::updated(function ($invoiceItem) {
            if ($invoiceItem->invoice_id) {
                $invoice = $invoiceItem->invoice ?? Invoice::find($invoiceItem->invoice_id);
                if ($invoice) {
                    $invoice->calculateAndUpdateTotals();
                }
            }
        });
        
        // Update invoice totals after deleting an item
        static::deleted(function ($invoiceItem) {
            if ($invoiceItem->invoice_id) {
                $invoice = $invoiceItem->invoice ?? Invoice::find($invoiceItem->invoice_id);
                if ($invoice) {
                    $invoice->calculateAndUpdateTotals();
                }
            }
        });
    }

    /**
     * Get the invoice that owns the invoice item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product associated with the invoice item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the child associated with the invoice item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
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
     * Get the subtotal before discount.
     *
     * @return int
     */
    public function getSubtotal(): int
    {
        return $this->price * $this->quantity;
    }

    /**
     * Get the formatted price.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedPrice(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->price, $includeCurrency);
    }

    /**
     * Get the total discount amount (discount per unit × quantity).
     *
     * @return int
     */
    public function getTotalDiscount(): int
    {
        return $this->discount * $this->quantity;
    }

    /**
     * Get the formatted discount per unit.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedDiscount(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->discount, $includeCurrency);
    }

    /**
     * Get the formatted total discount amount.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedTotalDiscount(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->getTotalDiscount(), $includeCurrency);
    }

    /**
     * Get the formatted total.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedTotal(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->total, $includeCurrency);
    }

    /**
     * Get the formatted subtotal.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedSubtotal(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->getSubtotal(), $includeCurrency);
    }

    /**
     * Check if the item has a discount.
     *
     * @return bool
     */
    public function hasDiscount(): bool
    {
        return $this->discount > 0;
    }

    /**
     * Get the discount percentage if applicable.
     * Based on discount per unit vs unit price.
     *
     * @return float|null
     */
    public function getDiscountPercentage(): ?float
    {
        if ($this->discount <= 0 || $this->price <= 0) {
            return null;
        }

        return ($this->discount / $this->price) * 100;
    }

    /**
     * Scope a query to only include items for a specific invoice.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $invoiceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Scope a query to only include items for a specific child.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $childId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForChild($query, $childId)
    {
        return $query->where('child_id', $childId);
    }

    /**
     * Scope a query to only include items with discounts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithDiscount($query)
    {
        return $query->where('discount', '>', 0);
    }
}
