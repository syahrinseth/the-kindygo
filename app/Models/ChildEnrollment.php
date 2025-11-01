<?php

namespace App\Models;

use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentType;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChildEnrolment extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'centre_id',
        'child_id',
        'product_id',
        'status',
        'billed_every',
        'date_start',
        'date_end',
        'type',
        'additional_products',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ChildEnrolmentStatus::class,
        'billed_every' => ChildEnrolmentBilledEvery::class,
        'type' => ChildEnrolmentType::class,
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'additional_products' => 'array',
    ];

    /**
     * Get the tenant that owns the enrolment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the centre associated with the enrolment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * Get the child that owns the enrolment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the product associated with the enrolment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the invoice items associated with this enrolment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the invoices that have items for this enrolment.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getInvoices()
    {
        return Invoice::whereIn(
            'id',
            $this->invoiceItems()->pluck('invoice_id')
        )->get();
    }

    /**
     * Scope a query to only include active enrolments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', ChildEnrolmentStatus::ACTIVE);
    }

    /**
     * Scope a query to only include current enrolments (active and not expired).
     * This includes enrolments that:
     * - Have status = 'active'
     * - Have already started (date_start <= now)
     * - Have not ended yet (date_end is null OR date_end >= now)
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $query->where('status', ChildEnrolmentStatus::ACTIVE)
            ->where('date_start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('date_end')
                    ->orWhere('date_end', '>=', now());
            });
    }

    /**
     * Scope a query to include active enrolments that haven't ended yet.
     * This includes enrolments that may have future start dates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotEnded($query)
    {
        return $query->where('status', ChildEnrolmentStatus::ACTIVE)
            ->where(function ($query) {
                $query->whereNull('date_end')
                    ->orWhere('date_end', '>=', now());
            });
    }

    /**
     * Scope a query to include enrolments that are running today.
     * This includes enrolments where today is between start and end dates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRunningToday($query)
    {
        return $query->where('status', ChildEnrolmentStatus::ACTIVE)
            ->where('date_start', '<=', now())
            ->where(function ($query) {
                $query->whereNull('date_end')
                    ->orWhere('date_end', '>=', now()->startOfDay());
            });
    }

    /**
     * Scope a query to filter by enrolment type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  ChildEnrolmentType  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, ChildEnrolmentType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the enrolment is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === ChildEnrolmentStatus::ACTIVE
            && $this->date_start <= now()
            && (is_null($this->date_end) || $this->date_end >= now());
    }

    /**
     * Check if the enrolment has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return !is_null($this->date_end) && $this->date_end < now();
    }
}
