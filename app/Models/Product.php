<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'status',
        'type',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ProductStatus::class,
        'type' => ProductType::class,
    ];

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
     * Get the tenant that owns the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the invoice items for this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Scope a query to filter by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by priority.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $priority
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class, 'product_centre')
            ->withTimestamps();
    }

    /**
     * Get the prices for this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderBy('start_date', 'desc');
    }

    /**
     * Get the current active price for this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function currentPrice()
    {
        return $this->hasOne(ProductPrice::class)
                    ->current()
                    ->orderBy('start_date', 'desc');
    }

    /**
     * Get the active price for a specific date.
     *
     * @param string|null $date
     * @return ProductPrice|null
     */
    public function getPriceOn($date = null)
    {
        return $this->prices()->activeOn($date)->first();
    }

    /**
     * Get the formatted current price.
     *
     * @return string
     */
    public function getCurrentFormattedPrice(): string
    {
        $currentPrice = $this->currentPrice;
        return $currentPrice ? $currentPrice->formatted_price : 'No price set';
    }
}
