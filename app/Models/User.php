<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Exception;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasDefaultTenant, HasMedia, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, InteractsWithMedia, Notifiable;

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
        'profile_completed',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $tenant = Filament::getTenant();
            $user->current_tenant_id = $tenant ? $tenant->id : null;
        });

        static::created(function ($user) {
            $tenant = Filament::getTenant();
            if (! empty($tenant)) {
                $user->tenants()->attach($tenant);
            }
        });
    }

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
            'profile_completed' => 'boolean',
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
     * 3. The latest accessed tewnant
     * 4. The first available tenant
     */
    public function getDefaultTenant(?Panel $panel = null): ?Model
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
        if (! $this->current_tenant_id) {
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
     * TODO: verify if this is still needed
     * Helper method to get current centre directly
     */
    public function getCurrentCentre(): ?Centre
    {
        if (! $this->current_tenant_id) {
            return null;
        }

        // Get the tenant_user record for current tenant
        $tenantUser = DB::table('tenant_user')
            ->where('user_id', $this->id)
            ->where('tenant_id', $this->current_tenant_id)
            ->first();

        if (! $tenantUser || ! $tenantUser->current_centre_id) {
            return null;
        }

        return Centre::find($tenantUser->current_centre_id);
    }

    /**
     * Get centres for the current tenant that the user has access to
     */
    public function getCentresForCurrentTenant(): Collection
    {
        if (! $this->current_tenant_id) {
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
        if (! $this->current_tenant_id) {
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
     * @return HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the children associated with this user.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'child_user')
            ->withPivot('relationship_type')
            ->using(ChildUser::class)
            ->withTimestamps();
    }

    /**
     * Get the user's profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's address.
     */
    public function userAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class);
    }

    /**
     * Get the user's office information.
     */
    public function officeInfo(): HasOne
    {
        return $this->hasOne(UserOfficeInfo::class);
    }

    /**
     * Register media collections for the user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('mykad')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->singleFile()
            ->useDisk('private');

        $this->addMediaCollection('photo')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('private');

        $this->addMediaCollection('immunization_card')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->singleFile()
            ->useDisk('private');
    }

    /**
     * Register media conversions for the user.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('photo');

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->quality(90)
            ->performOnCollections('mykad', 'immunization_card');
    }

    /**
     * Get the user's avatar URL for Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        $media = $this->getFirstMedia('photo');

        if (! $media) {
            return null;
        }

        // Use the thumb conversion if available, otherwise use the original
        if ($media->hasGeneratedConversion('thumb')) {
            return $media->getUrl('thumb');
        }

        return $media->getUrl();
    }

    // E-Invoice Helper Methods

    /**
     * Get the appropriate identification scheme for e-invoice.
     * Returns 'TIN', 'NRIC', or 'PASSPORT' based on available data.
     */
    public function getEInvoiceSchemeId(): string
    {
        if ($this->profile) {
            return $this->profile->getEInvoiceSchemeId();
        }

        // Default to NRIC for Malaysian customers
        return 'NRIC';
    }

    /**
     * Get the identification value for e-invoice.
     *
     * @throws Exception if no valid identification is available
     */
    public function getEInvoiceIdentification(): string
    {
        if ($this->profile) {
            return $this->profile->getEInvoiceIdentification();
        }

        // If no profile available, throw exception
        throw new Exception("Customer '{$this->name}' must have a valid profile with NRIC or Passport number for e-Invoice submission.");
    }

    public function getEInvoiceTIN(): string
    {
        if ($this->profile) {
            return $this->profile->getEInvoiceTIN();
        }

        // If no profile available, throw exception
        throw new Exception("Customer '{$this->name}' must have a valid profile with TIN for e-Invoice submission.");
    }

    /**
     * Check if the user has complete address information for e-invoice.
     */
    public function hasCompleteAddress(): bool
    {
        return $this->userAddress && $this->userAddress->isComplete();
    }

    /**
     * Check if the user has valid identification (NRIC or Passport).
     */
    public function hasValidIdentification(): bool
    {
        return $this->profile &&
               (! empty($this->profile->nric) || ! empty($this->profile->passport));
    }

    /**
     * Check if the user has a valid TIN.
     */
    public function hasValidTin(): bool
    {
        return $this->profile && ! empty($this->profile->tin);
    }

    /**
     * Check if the user is ready for e-invoice generation.
     * Requires: complete address, valid identification (NRIC/Passport), and TIN.
     */
    public function eInvoiceReady(): bool
    {
        return $this->hasCompleteAddress() &&
               $this->hasValidIdentification() &&
               $this->hasValidTin();
    }

    /**
     * Get a list of missing e-invoice requirements.
     */
    public function getEInvoiceMissingRequirements(): array
    {
        $missing = [];

        if (! $this->hasValidIdentification()) {
            $missing[] = 'NRIC or Passport';
        }

        if (! $this->hasValidTin()) {
            $missing[] = 'TIN (Tax ID)';
        }

        if (! $this->hasCompleteAddress()) {
            $missing[] = 'Complete address';
        }

        return $missing;
    }

    /**
     * Get formatted address for e-invoice display.
     */
    public function getFormattedAddress(): string
    {
        if ($this->userAddress) {
            return $this->userAddress->getFormattedAddress();
        }

        return '';
    }

    /**
     * Get state name from user's address.
     */
    public function getStateName(): string
    {
        if ($this->userAddress) {
            return $this->userAddress->getStateName();
        }

        return '';
    }

    public function scopeEInvoiceReady(Builder $query): Builder
    {
        return $query->whereHas('userAddress', function (Builder $addressQuery) {
            // Check if address is complete
            $addressQuery->whereNotNull('address')
                ->whereNotNull('city')
                ->whereNotNull('postal_code')
                ->whereNotNull('state_code');
        })
            ->whereHas('profile', function (Builder $profileQuery) {
                // Check if profile has TIN and identification
                $profileQuery->whereNotNull('tin')
                    ->where('tin', '!=', '')
                    ->where(function (Builder $idQuery) {
                        $idQuery->where(function (Builder $nricQuery) {
                            $nricQuery->whereNotNull('nric')->where('nric', '!=', '');
                        })
                            ->orWhere(function (Builder $passportQuery) {
                                $passportQuery->whereNotNull('passport')->where('passport', '!=', '');
                            });
                    });
            });
    }

    /**
     * Scope to get users missing identification (NRIC or Passport).
     */
    public function scopeMissingIdentification(Builder $query): Builder
    {
        return $query->whereDoesntHave('profile')
            ->orWhereHas('profile', function (Builder $q) {
                $q->where(function (Builder $idQuery) {
                    $idQuery->whereNull('nric')->orWhere('nric', '');
                })
                    ->where(function (Builder $passportQuery) {
                        $passportQuery->whereNull('passport')->orWhere('passport', '');
                    });
            });
    }

    /**
     * Scope to get users missing TIN.
     */
    public function scopeMissingTin(Builder $query): Builder
    {
        return $query->whereDoesntHave('profile')
            ->orWhereHas('profile', function (Builder $q) {
                $q->whereNull('tin')->orWhere('tin', '');
            });
    }

    /**
     * Scope to get users missing complete address.
     */
    public function scopeMissingCompleteAddress(Builder $query): Builder
    {
        return $query->whereDoesntHave('userAddress')
            ->orWhereHas('userAddress', function (Builder $q) {
                $q->whereNull('address')
                    ->orWhere('address', '')
                    ->orWhereNull('city')
                    ->orWhere('city', '')
                    ->orWhereNull('postal_code')
                    ->orWhere('postal_code', '')
                    ->orWhereNull('state_code')
                    ->orWhere('state_code', '');
            });
    }
}
