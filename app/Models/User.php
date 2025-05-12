<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\HasTenants;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the tenant that owns the user.
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant)->exists();
    }

    /**
     * Get the default tenant for the user.
     * This will return either:
     * 1. The directly assigned tenant
     * 2. The user's personal tenant
     * 3. The latest accessed tenant
     * 4. The first available tenant
     */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        // First try the directly assigned tenant
        if ($this->tenant_id && $this->tenant) {
            return $this->tenant;
        }

        // Then try to find a personal tenant
        $personalTenant = $this->tenants()
            ->where('personal_tenant', true)
            ->first();
        if ($personalTenant) {
            return $personalTenant;
        }

        // Try to get the latest accessed tenant
        $latestTenant = $this->latestTenant()->first();
        if ($latestTenant) {
            return $latestTenant;
        }

        // Finally, fall back to the first available tenant
        return $this->tenants()->first();
    }

    /**
     * Get the latest tenant relationship
     */
    public function latestTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id')
            ->latest('tenant_user.updated_at')
            ->join('tenant_user', 'tenants.id', '=', 'tenant_user.tenant_id')
            ->where('tenant_user.user_id', $this->id);
    }

    /**
     * Set the current tenant for the user
     */
    public function setCurrentTenant(?Tenant $tenant): void
    {
        $this->tenant()->associate($tenant);
        $this->save();

        if ($tenant) {
            // Update the last access timestamp in the pivot table
            $this->tenants()->updateExistingPivot($tenant->id, [
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Set the default tenant for the user
     */
    public function setDefaultTenant(Tenant $tenant): void
    {
        if ($this->canAccessTenant($tenant)) {
            $this->tenant()->associate($tenant);
            $this->save();
        }
    }
}
