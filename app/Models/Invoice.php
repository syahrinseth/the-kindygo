<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
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
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->number)) {
                $invoice->number = $invoice->generateInvoiceNumber();
            }
        });
    }

    /**
     * Generate a unique invoice number using format: #{centre_code}/{year}/{running_number}
     *
     * @return string
     */
    public function generateInvoiceNumber(): string
    {
        $date = $this->date ?? now();
        $year = $date->format('Y');
        
        // Get centre code - use the dedicated code field if available, otherwise fallback to name
        $centre = $this->centre ?? Centre::find($this->centre_id);
        $centreCode = $centre->code ?? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $centre->name ?? 'CENTRE'));
        
        // Generate sequential number unique to tenant_id, centre_id, and year
        $sequentialNumber = $this->getNextSequentialNumber($year);
        $runningNumber = str_pad($sequentialNumber, 4, '0', STR_PAD_LEFT);
        
        // Format: #{centre_code}/{year}/{running_number}
        $number = "#{$centreCode}/{$year}/{$runningNumber}";
        
        return $number;
    }

    /**
     * Get the next sequential number for invoice generation.
     * Numbers are unique based on tenant_id, centre_id, and year.
     *
     * @param string $year
     * @return int
     */
    private function getNextSequentialNumber(string $year): int
    {
        $lastInvoice = static::where('tenant_id', $this->tenant_id)
            ->where('centre_id', $this->centre_id)
            ->whereYear('date', $year)
            ->orderBy('created_at', 'desc')
            ->first();
            
        if (!$lastInvoice) {
            return 1;
        }
        
        // Extract number from the last invoice using the new format #{CODE}/{YEAR}/{NUMBER}
        preg_match('/\#[A-Z0-9]+\/\d{4}\/(\d+)$/', $lastInvoice->number, $matches);
        $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;
        
        return $lastNumber + 1;
    }

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
     * Scope a query to filter invoices based on current user's role and permissions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Models\User|null  $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCurrentUser($query, $user = null)
    {
        if (!$user) {
            $user = \Illuminate\Support\Facades\Auth::user();
        }

        if (!$user || !$user->current_tenant_id) {
            return $query->whereRaw('1 = 0'); // Return empty result if no user or tenant
        }
        
        // Filter by current tenant
        return $query->where('tenant_id', $user->current_tenant_id);
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
     * Get the payments associated with the invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }
    
    /**
     * Get the total amount paid for this invoice (only from 'paid' payments).
     *
     * @return int
     */
    public function getTotalPaid(): int
    {
        return $this->payments()
            ->where('status', PaymentStatus::PAID)
            ->sum('invoice_payment.amount');
    }
    
    /**
     * Get the remaining balance for this invoice.
     *
     * @return int
     */
    public function getRemainingBalance(): int
    {
        return $this->total - $this->getTotalPaid();
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
