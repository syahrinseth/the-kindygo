<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\Centre;
use App\Models\Invoice;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('accessPanel', $this);
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
        'current_tenant_id',
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
        return $this->belongsToMany(Tenant::class)
            ->withTimestamps();
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

    /**
     * Get the centres that belong to the user
     */
    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class)
            ->withTimestamps();
    }

    /**
     * Get the user's current centre based on current tenant
     */
    public function currentCentre()
    {
        if (!$this->current_tenant_id) {
            // Return an empty BelongsTo relationship when no current tenant
            return $this->belongsTo(Centre::class, 'non_existent_column');
        }

        // Create a HasOneThrough relationship via tenant_user table
        return $this->hasOneThrough(
            Centre::class,           // Final model we want
            TenantUser::class,       // Intermediate model
            'user_id',               // Foreign key on tenant_user table
            'id',                    // Foreign key on centres table
            'id',                    // Local key on users table
            'current_centre_id'      // Local key on tenant_user table
        )->where('tenant_user.tenant_id', $this->current_tenant_id);
    }

    /**
     * Helper method to get current centre directly
     */
    public function getCurrentCentre(): ?Centre
    {
        if (!$this->current_tenant_id) {
            return null;
        }

        // Get the tenant_user record for current tenant
        $tenantUser = DB::table('tenant_user')
            ->where('user_id', $this->id)
            ->where('tenant_id', $this->current_tenant_id)
            ->first();

        if (!$tenantUser || !$tenantUser->current_centre_id) {
            return null;
        }

        return Centre::find($tenantUser->current_centre_id);
    }

    /**
     * Get centres for the current tenant that the user has access to
     */
    public function getCentresForCurrentTenant(): Collection
    {
        if (!$this->current_tenant_id) {
            return collect();
        }

        // Use the scope from Centre model for better reusability
        return Centre::whereHas('users', function ($query) {
                $query->where('users.id', $this->id);
            })
            ->get();
    }

    /**
     * Set the current centre for the user
     */
    public function setCurrentCentre(?Centre $centre): void
    {
        if (!$this->current_tenant_id) {
            return;
        }

        if ($centre && $this->centres()->where('centre_id', $centre->id)->exists()) {
            // Update the tenant_user record with the current centre
            DB::table('tenant_user')
                ->where('user_id', $this->id)
                ->where('tenant_id', $this->current_tenant_id)
                ->update(['current_centre_id' => $centre->id]);
        } else {
            // Clear the current centre if no centre is provided or user doesn't have access
            DB::table('tenant_user')
                ->where('user_id', $this->id)
                ->where('tenant_id', $this->current_tenant_id)
                ->update(['current_centre_id' => null]);
        }
    }

    /**
     * Get the invoices associated with this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the children associated with this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_user')
            ->withPivot('relationship_type')
            ->using(ChildUser::class)
            ->withTimestamps();
    }
}
