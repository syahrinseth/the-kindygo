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
        // Try to find a personal tenant first
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
    public function latestTenant(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->orderByPivot('updated_at', 'desc')
            ->limit(1);
    }

    /**
     * Set the current tenant for the user
     */
    public function setCurrentTenant(?Tenant $tenant): void
    {
        if ($tenant && $this->canAccessTenant($tenant)) {
            // Update the last access timestamp in the pivot table
            $this->tenants()->updateExistingPivot($tenant->id, [
                'updated_at' => now(),
            ]);
        }
    }
}
