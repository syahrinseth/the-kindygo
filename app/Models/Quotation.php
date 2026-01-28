<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Quotation extends Model
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
        'child_id',
        'number',
        'date',
        'valid_until',
        'status',
        'converted_invoice_id',
        'total_items',
        'total_amount',
        'total',
        'terms_conditions',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime',
        'valid_until' => 'datetime',
        'status' => QuotationStatus::class,
        'total_items' => 'integer',
        'total_amount' => 'integer',
        'total' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quotation) {
            if (empty($quotation->number)) {
                $quotation->number = $quotation->generateQuotationNumber();
            }
        });

        static::retrieved(function ($quotation) {
            // Auto-update expired status if needed
            if ($quotation->status !== QuotationStatus::CONVERTED
                && $quotation->status !== QuotationStatus::EXPIRED
                && $quotation->status !== QuotationStatus::REJECTED
                && $quotation->isExpired()) {
                $quotation->update(['status' => QuotationStatus::EXPIRED]);
            }
        });

        static::addGlobalScope(new TenantScope);
    }

    /**
     * Generate a unique quotation number using format: QUO/{centre_code}/{year}/{running_number}
     */
    public function generateQuotationNumber(): string
    {
        $date = $this->date ?? now();
        $year = $date->format('Y');

        // Get centre code - use the dedicated code field if available, otherwise fallback to name
        $centre = $this->centre ?? Centre::find($this->centre_id);

        // Generate preschool-friendly centre code
        $centreCode = Centre::generateCentreCode($centre);

        // Generate sequential number unique to tenant_id, centre_id, and year
        $sequentialNumber = $this->getNextSequentialNumber($year);
        $runningNumber = str_pad($sequentialNumber, 4, '0', STR_PAD_LEFT);

        // Format: QUO/{centre_code}/{year}/{running_number}
        $number = "QUO/{$centreCode}/{$year}/{$runningNumber}";

        return $number;
    }

    /**
     * Get the next sequential number for quotation generation.
     * Numbers are unique based on tenant_id, centre_id, and year.
     */
    private function getNextSequentialNumber(string $year): int
    {
        $lastQuotation = static::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenant_id)
            ->where('centre_id', $this->centre_id)
            ->whereYear('date', $year)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $lastQuotation) {
            return 1;
        }

        // Extract number from the last quotation using the format QUO{CODE}/{YEAR}/{NUMBER}
        preg_match('/[A-Z0-9]+\/\d{4}\/(\d+)$/', $lastQuotation->number, $matches);

        $lastNumber = isset($matches[1]) ? (int) $matches[1] : 0;

        return $lastNumber + 1;
    }

    /**
     * Calculate and update the totals for this quotation.
     */
    public function calculateAndUpdateTotals(): void
    {
        $totalItems = $this->quotationItems()->count();
        $totalAmount = $this->quotationItems()->sum('total');

        $this->update([
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'total' => $totalAmount,
        ]);
    }

    /**
     * Check if the quotation is expired.
     */
    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    /**
     * Get the tenant that owns the quotation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the centre that owns the quotation.
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * Get the user that owns the quotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the child that owns the quotation.
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the invoice this quotation was converted to.
     */
    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    /**
     * Get the quotation items for this quotation.
     */
    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * Alias for quotationItems relationship.
     */
    public function items(): HasMany
    {
        return $this->quotationItems();
    }

    /**
     * Scope a query to filter by current user role.
     */
    public function scopeForCurrentUser(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Super Admin sees all
        if ($user->is_super_admin) {
            return $query;
        }

        // Tenant owners see all quotations in their tenant
        $tenant = $user->currentTenant();
        if ($tenant && $tenant->owner_id === $user->id) {
            return $query->where('tenant_id', $user->current_tenant_id);
        }

        // Parents see only their own quotations
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope a query to filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to filter by centre.
     */
    public function scopeForCentre(Builder $query, int $centreId): Builder
    {
        return $query->where('centre_id', $centreId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeWithStatus(Builder $query, QuotationStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to get expired quotations.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('valid_until', '<', now())
            ->whereNotIn('status', [
                QuotationStatus::CONVERTED->value,
                QuotationStatus::EXPIRED->value,
                QuotationStatus::REJECTED->value,
            ]);
    }
}
