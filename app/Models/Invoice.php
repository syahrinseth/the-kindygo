<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
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
}
