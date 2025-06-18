<?php

namespace App\Models;

use App\Enums\ChildEnrollmentStatus;
use App\Enums\ChildEnrollmentBilledEvery;
use App\Enums\ChildEnrollmentType;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChildEnrollment extends Model
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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ChildEnrollmentStatus::class,
        'billed_every' => ChildEnrollmentBilledEvery::class,
        'type' => ChildEnrollmentType::class,
        'date_start' => 'datetime',
        'date_end' => 'datetime',
    ];

    /**
     * Get the tenant that owns the enrollment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the centre associated with the enrollment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * Get the child that owns the enrollment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get the product associated with the enrollment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope a query to only include active enrollments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', ChildEnrollmentStatus::ACTIVE);
    }

    /**
     * Scope a query to only include current enrollments (active and not expired).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $query->where('status', ChildEnrollmentStatus::ACTIVE)
                    ->where('date_start', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('date_end')
                              ->orWhere('date_end', '>=', now());
                    });
    }

    /**
     * Scope a query to filter by enrollment type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  ChildEnrollmentType  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, ChildEnrollmentType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if the enrollment is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === ChildEnrollmentStatus::ACTIVE 
               && $this->date_start <= now() 
               && (is_null($this->date_end) || $this->date_end >= now());
    }

    /**
     * Check if the enrollment has expired.
     *
     * @return bool
     */
    public function hasExpired(): bool
    {
        return !is_null($this->date_end) && $this->date_end < now();
    }
}
