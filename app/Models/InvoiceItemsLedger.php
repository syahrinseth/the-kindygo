<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemsLedger extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'centre_id',
        'ledger_type',
        'invoice_id',
        'invoice_item_id',
        'payment_id',
        'child_id',
        'product_id',
        'description',
        'debit_amount',
        'credit_amount',
        'balance_amount',
        'paid',
        'priority',
        'reference_data',
        'recorded_at',
    ];

    protected $casts = [
        'paid' => 'boolean',
        'reference_data' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get formatted debit amount in currency.
     */
    public function getFormattedDebitAmount(): string
    {
        return 'RM '.number_format($this->debit_amount / 100, 2);
    }

    /**
     * Get formatted credit amount in currency.
     */
    public function getFormattedCreditAmount(): string
    {
        return 'RM '.number_format($this->credit_amount / 100, 2);
    }

    /**
     * Get formatted balance amount in currency.
     */
    public function getFormattedBalanceAmount(): string
    {
        return 'RM '.number_format($this->balance_amount / 100, 2);
    }
}
