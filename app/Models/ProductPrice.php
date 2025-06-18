<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class ProductPrice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'price',
        'start_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the product that owns the price.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Format the price for display.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'RM' . number_format($this->price / 100, 2);
    }

    /**
     * Scope to get active prices for a specific date.
     */
    public function scopeActiveOn($query, $date = null)
    {
        $date = $date ?? now()->toDateString();
        
        return $query->where('start_date', '<=', $date)
                    ->where(function ($q) use ($date) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', $date);
                    });
    }

    /**
     * Scope to get current active prices.
     */
    public function scopeCurrent($query)
    {
        return $query->activeOn(now()->toDateString());
    }

    /**
     * Check if this price is currently active.
     */
    public function isActive($date = null): bool
    {
        $date = $date ?? now()->toDateString();
        
        return $this->start_date <= $date && 
               ($this->end_date === null || $this->end_date >= $date);
    }

    /**
     * Check if this price is expired.
     */
    public function isExpired(): bool
    {
        return $this->end_date !== null && $this->end_date < now()->toDateString();
    }

    /**
     * Check if this price is future.
     */
    public function isFuture(): bool
    {
        return $this->start_date > now()->toDateString();
    }
}
