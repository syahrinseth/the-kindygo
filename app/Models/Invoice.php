<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'tenant_id',
        'centre_id',
        'user_id',
        'date',
        'due_at',
        'status',
        'total_items',
        'total_discounts',
        'total_amount',
        'total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime',
        'due_at' => 'datetime',
        'status' => InvoiceStatus::class,
        'total_items' => 'integer',
        'total_discounts' => 'integer',
        'total_amount' => 'integer',
        'total' => 'integer',
    ];

    /**
     * Get the tenant that owns the invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the centre that owns the invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * Get the user that owns the invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    /**
     * Check if the invoice is overdue.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::OVERDUE || 
               ($this->status === InvoiceStatus::PENDING && $this->due_at < now());
    }

    /**
     * Scope a query to only include invoices for a specific tenant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $tenantId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include invoices for a specific centre.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $centreId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCentre($query, $centreId)
    {
        return $query->where('centre_id', $centreId);
    }

    /**
     * Scope a query to only include overdue invoices.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where(function ($query) {
            $query->where('status', InvoiceStatus::OVERDUE)
                  ->orWhere(function ($query) {
                      $query->where('status', InvoiceStatus::PENDING)
                            ->where('due_at', '<', now());
                  });
        });
    }

    /**
     * Scope a query to only include invoices with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Enums\InvoiceStatus  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, InvoiceStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Update the status based on the due date.
     * This is useful for automatically marking invoices as overdue.
     *
     * @return void
     */
    public function updateStatusBasedOnDueDate(): void
    {
        if ($this->status === InvoiceStatus::PENDING && $this->due_at < now()) {
            $this->status = InvoiceStatus::OVERDUE;
            $this->save();
        }
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
        
        // When retrieving an invoice, check if it should be marked as overdue
        static::retrieved(function (Invoice $invoice) {
            $invoice->updateStatusBasedOnDueDate();
        });
    }
    
    /**
     * Format a monetary amount for display.
     *
     * @param int $amount The amount in cents
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public static function formatMoney(int $amount, bool $includeCurrency = true): string
    {
        $formatted = number_format($amount / 100, 2);
        return $includeCurrency ? '$' . $formatted : $formatted;
    }
    
    /**
     * Get the formatted total amount.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedTotal(bool $includeCurrency = true): string
    {
        return self::formatMoney($this->total, $includeCurrency);
    }
    
    /**
     * Generate a PDF for this invoice.
     *
     * @return string The path to the generated PDF
     */
    public function generatePdf(): string
    {
        // This is a placeholder for PDF generation functionality
        // You would integrate with a PDF library like Dompdf, TCPDF, or Snappy
        
        // For now, we'll just return a string indicating the feature is not implemented
        return 'PDF generation not implemented yet';
    }
}
