<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'effective_date' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'effective_date' => 'date',
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
            $invoiceItem->calculateBalance();
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
     * @return BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product associated with the invoice item.
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the child associated with the invoice item.
     *
     * @return BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the child enrolment associated with the invoice item.
     *
     * @return BelongsTo
     */
    public function childEnrolment(): BelongsTo
    {
        return $this->belongsTo(ChildEnrolment::class);
    }

    /**
     * Get the child enrolments associated with this invoice item through a pivot table.
     *
     * @return BelongsToMany
     */
    public function childEnrolments(): BelongsToMany
    {
        return $this->belongsToMany(ChildEnrolment::class, 'child_enrolment_invoice_item', 'invoice_item_id', 'child_enrolment_id')
            ->withPivot(['quantity', 'notes'])
            ->withTimestamps();
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
     * Get the formatted paid amount.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedPaidAmount(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->paid_amount, $includeCurrency);
    }

    /**
     * Get the formatted balance amount.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedBalanceAmount(bool $includeCurrency = true): string
    {
        return Invoice::formatMoney($this->balance_amount, $includeCurrency);
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
     * Check if the item is fully paid.
     *
     * @return bool
     */
    public function isFullyPaid(): bool
    {
        return $this->paid;
    }

    /**
     * Check if the item is partially paid.
     *
     * @return bool
     */
    public function isPartiallyPaid(): bool
    {
        return !$this->paid && $this->paid_amount > 0;
    }

    /**
     * Check if the item is unpaid.
     *
     * @return bool
     */
    public function isUnpaid(): bool
    {
        return $this->paid_amount <= 0;
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
     * @param Builder $query
     * @param  int  $invoiceId
     * @return Builder
     */
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Scope a query to only include items for a specific child.
     *
     * @param Builder $query
     * @param  int  $childId
     * @return Builder
     */
    public function scopeForChild($query, $childId)
    {
        return $query->where('child_id', $childId);
    }

    /**
     * Scope a query to only include items with discounts.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithDiscount($query)
    {
        return $query->where('discount', '>', 0);
    }

    /**
     * Scope a query to only include paid items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePaid($query)
    {
        return $query->where('paid', true);
    }

    /**
     * Scope a query to only include unpaid items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnpaid($query)
    {
        return $query->where('paid', false);
    }

    /**
     * Scope a query to only include partially paid items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePartiallyPaid($query)
    {
        return $query->where('paid', false)->where('paid_amount', '>', 0);
    }

    /**
     * Scope a query to filter by type.
     *
     * @param Builder $query
     * @param  InvoiceItemType|string  $type
     * @return Builder
     */
    public function scopeOfType($query, $type)
    {
        if ($type instanceof InvoiceItemType) {
            return $query->where('type', $type->value);
        }

        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by effective date range.
     *
     * @param Builder $query
     * @param  string  $from
     * @param  string  $to
     * @return Builder
     */
    public function scopeEffectiveDateBetween($query, $from, $to)
    {
        return $query->whereBetween('effective_date', [$from, $to]);
    }

    /**
     * Scope a query to filter by specific effective date.
     *
     * @param Builder $query
     * @param  string  $date
     * @return Builder
     */
    public function scopeEffectiveDate($query, $date)
    {
        return $query->whereDate('effective_date', $date);
    }

    /**
     * Scope a query to only include product items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeProducts($query)
    {
        return $query->where('type', InvoiceItemType::PRODUCT->value);
    }

    /**
     * Scope a query to only include invoice discount items.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeInvoiceDiscounts($query)
    {
        return $query->where('type', InvoiceItemType::INVOICE_DISCOUNT->value);
    }

    /**
     * Get the type label for display.
     *
     * @return string
     */
    public function getTypeLabel(): string
    {
        return $this->type?->label() ?? '';
    }

    /**
     * Check if the item is a product type.
     *
     * @return bool
     */
    public function isProduct(): bool
    {
        return $this->type === InvoiceItemType::PRODUCT;
    }

    /**
     * Check if the item is an invoice discount type.
     *
     * @return bool
     */
    public function isInvoiceDiscount(): bool
    {
        return $this->type === InvoiceItemType::INVOICE_DISCOUNT;
    }

    /**
     * Update payment status based on invoice status.
     *
     * @return void
     */
    public function updatePaymentStatusFromInvoice(): void
    {
        if (!$this->invoice) {
            return;
        }

        $invoiceStatus = $this->invoice->status;

        if ($invoiceStatus === InvoiceStatus::PAID->value) {
            // If invoice is paid in full, mark all items as paid
            $this->paid_amount = $this->total;
            $this->balance_amount = 0;
            $this->paid = true;
        } else {
            // Calculate balance and determine paid status
            $this->calculateBalance();
        }
    }
}
