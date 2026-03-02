<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Models\Scopes\UserCentreScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Centre extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'campus_id',
        'slug',
        'code',
        'name',
        'status',
        'phone',
        'email',
        'address_1',
        'address_2',
        'postal_code',
        'city',
        'state',
        'meta_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
        'meta_data' => 'array',
    ];

    /**
     * Generate a centre code from centre name by taking first letter(s) of each word.
     * Used for invoice and quotation numbering.
     *
     * Examples:
     * - "Happy Kids Centre" → "HKC"
     * - "Sunny Preschool" → "SP"
     * - "Little Stars Kindergarten" → "LSK"
     * - "ABC" → "ABC"
     *
     * @return string Centre code (2-4 characters, e.g., 'HKC', 'SP', 'MB')
     */
    public static function generateCentreCode(?Centre $centre): string
    {
        if (! $centre) {
            return 'CTR'; // Default centre code
        }

        // If centre has a dedicated code field, use it
        if (! empty($centre->code)) {
            return strtoupper($centre->code);
        }

        // Generate code from centre name
        $name = $centre->name ?? 'Centre';

        // Remove special characters, keep only letters, numbers, and spaces
        $cleanName = preg_replace('/[^A-Za-z0-9\s]/', '', $name);

        // Split into words and filter empty strings
        $words = array_filter(explode(' ', $cleanName), fn ($word) => ! empty($word));

        if (empty($words)) {
            return 'CTR'; // Fallback if no valid words
        }

        $code = '';

        // Strategy 1: Take first letter of each word (up to 4 letters)
        foreach ($words as $word) {
            $code .= strtoupper($word[0]);
            if (strlen($code) >= 4) {
                break;
            }
        }

        // Strategy 2: If we only have 1 letter, take first 2-3 letters from first word
        if (strlen($code) === 1 && strlen($words[0]) >= 2) {
            $code = strtoupper(substr($words[0], 0, min(3, strlen($words[0]))));
        }

        // Ensure minimum 2 characters
        if (strlen($code) < 2) {
            $code = str_pad($code, 2, '0', STR_PAD_RIGHT);
        }

        // Limit to 4 characters maximum
        $code = substr($code, 0, 4);

        return $code;
    }

    /**
     * Get the tenant that owns the centre.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get the users associated with the centre
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * Get the invoices belonging to the centre.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the child enrolments belonging to the centre.
     */
    public function childEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class);
    }

    /**
     * Get the active child enrolments belonging to the centre.
     */
    public function activeChildEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class)->active();
    }

    /**
     * Get the current child enrolments belonging to the centre.
     */
    public function currentChildEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class)->current();
    }

    /**
     * Get the children associated with this centre.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'centre_child')
            ->using(CentreChild::class)
            ->withTimestamps();
    }

    /**
     * Add a child to this centre.
     *
     * @param  Child|int  $child  The child or child ID
     */
    public function addChild($child): void
    {
        $childId = $child instanceof Child ? $child->id : $child;

        if (! $this->children()->where('child_id', $childId)->exists()) {
            $this->children()->attach($childId);
        }
    }

    /**
     * Remove a child from this centre.
     *
     * @param  Child|int  $child  The child or child ID
     */
    public function removeChild($child): void
    {
        $childId = $child instanceof Child ? $child->id : $child;

        $this->children()->detach($childId);
    }

    /**
     * Check if a specific child is in this centre.
     *
     * @param  Child|int  $child  The child or child ID
     */
    public function hasChild($child): bool
    {
        $childId = $child instanceof Child ? $child->id : $child;

        return $this->children()->where('child_id', $childId)->exists();
    }

    /**
     * Get the products associated with the centre.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_centre');
    }

    /**
     * Get the payments associated with this centre.
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_centre')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    /**
     * Get the product prices associated with this centre.
     */
    public function prices(): BelongsToMany
    {
        return $this->belongsToMany(ProductPrice::class, 'price_centre', 'centre_id', 'product_price_id')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include centres that the authenticated user has access to
     * and that belong to the user's current tenant.
     */
    public function scopeForCurrentUser(Builder $query): Builder
    {
        return $query->withoutGlobalScope(UserCentreScope::class)
            ->withGlobalScope('user_centre', new UserCentreScope);
    }

    /**
     * Get the full address for this centre.
     * If centre address is not available, fallback to tenant address.
     */
    public function getFullAddressAttribute(): ?string
    {
        // Build centre address from individual fields
        $centreAddress = collect([
            $this->address_1,
            $this->address_2,
            $this->postal_code ? $this->postal_code.' '.$this->city : $this->city,
            $this->state,
        ])->filter()->implode(', ');

        // If centre address is empty, use tenant address as fallback
        if (empty($centreAddress) && $this->tenant) {
            $centreAddress = collect([
                $this->tenant->address_1,
                $this->tenant->address_2,
                $this->tenant->postal_code ? $this->tenant->postal_code.' '.$this->tenant->city : $this->tenant->city,
                $this->tenant->state,
            ])->filter()->implode(', ');
        }

        return ! empty($centreAddress) ? $centreAddress : null;
    }
}
