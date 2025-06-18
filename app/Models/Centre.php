<?php

namespace App\Models;

use App\Models\Tenant;
use App\Models\Campus;
use App\Models\Invoice;
use App\Models\Scopes\TenantScope;
use App\Models\Scopes\UserCentreScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'string',
    ];

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
     * Get the child enrollments belonging to the centre.
     */
    public function childEnrollments(): HasMany
    {
        return $this->hasMany(ChildEnrollment::class);
    }
    
    /**
     * Get the active child enrollments belonging to the centre.
     */
    public function activeChildEnrollments(): HasMany
    {
        return $this->hasMany(ChildEnrollment::class)->active();
    }
    
    /**
     * Get the current child enrollments belonging to the centre.
     */
    public function currentChildEnrollments(): HasMany
    {
        return $this->hasMany(ChildEnrollment::class)->current();
    }
    
    /**
     * Get the children associated with this centre.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
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
     * @param Child|int $child The child or child ID
     * @return void
     */
    public function addChild($child): void
    {
        $childId = $child instanceof Child ? $child->id : $child;
        
        if (!$this->children()->where('child_id', $childId)->exists()) {
            $this->children()->attach($childId);
        }
    }
    
    /**
     * Remove a child from this centre.
     *
     * @param Child|int $child The child or child ID
     * @return void
     */
    public function removeChild($child): void
    {
        $childId = $child instanceof Child ? $child->id : $child;
        
        $this->children()->detach($childId);
    }
    
    /**
     * Check if a specific child is in this centre.
     *
     * @param Child|int $child The child or child ID
     * @return bool
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
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCurrentUser(Builder $query): Builder
    {
        return $query->withoutGlobalScope(UserCentreScope::class)
            ->withGlobalScope('user_centre', new UserCentreScope());
    }
}
