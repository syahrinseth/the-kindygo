<?php

namespace App\Models;

use App\Enums\ChildStatus;
use App\Models\Scopes\BelongsToManyTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

class Child extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new BelongsToManyTenantScope);
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'children';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'patronymic',
        'mykid_no',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'cert_number',
        'position_of_child',
        'race',
        'religion',
        'languages',
        'allergies',
        'diseases',
        'family_clinic',
        'family_clinic_phone'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'position_of_child' => 'integer'
    ];

    /**
     * Get the full name of the child.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the name of the child (alias for full_name).
     *
     * @return string
     */
    public function getNameAttribute(): string
    {
        return $this->getFullNameAttribute();
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_child')
            ->withPivot('status')
            ->withTimestamps()
            ->using(TenantChild::class);
    }

    /**
     * Get the status of this child at a specific tenant.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @return ChildStatus|null The status of the child at the tenant or null if not associated
     */
    public function getStatusAtTenant($tenant): ?ChildStatus
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $pivotData = $this->tenants()->where('tenant_id', $tenantId)->first()?->pivot;

        return $pivotData ? $pivotData->status : null;
    }

    /**
     * Update the status of this child at a specific tenant.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @param ChildStatus $status The new status
     * @return bool Whether the update was successful
     */
    public function updateStatusAtTenant($tenant, ChildStatus $status): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $updated = $this->tenants()->updateExistingPivot($tenantId, [
            'status' => $status,
        ]);

        return $updated > 0;
    }

    /**
     * Activate this child at a specific tenant.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @return bool Whether the update was successful
     */
    public function activateAtTenant($tenant): bool
    {
        return $this->updateStatusAtTenant($tenant, ChildStatus::ACTIVE);
    }

    /**
     * Mark this child as returning at a specific tenant.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @return bool Whether the update was successful
     */
    public function markAsReturningAtTenant($tenant): bool
    {
        return $this->updateStatusAtTenant($tenant, ChildStatus::RETURN);
    }

    /**
     * Mark this child as alumni at a specific tenant.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @return bool Whether the update was successful
     */
    public function markAsAlumniAtTenant($tenant): bool
    {
        return $this->updateStatusAtTenant($tenant, ChildStatus::ALUMNI);
    }

    /**
     * Associate this child with a tenant, setting the initial status.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @param ChildStatus $status The initial status (defaults to NEW)
     * @return void
     */
    public function addToTenant($tenant, ChildStatus $status = ChildStatus::NEW): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        if (!$this->tenants()->where('tenant_id', $tenantId)->exists()) {
            $this->tenants()->attach($tenantId, [
                'status' => $status,
            ]);
        }
    }

    /**
     * Remove this child from a tenant.
     *
     * @param Tenant|int $tenant The tenant or tenant ID
     * @return void
     */
    public function removeFromTenant($tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $this->tenants()->detach($tenantId);
    }

    /**
     * Get the users associated with this child.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'child_user')
            ->withPivot('relationship_type')
            ->using(ChildUser::class)
            ->withTimestamps();
    }

    /**
     * Get the centres associated with this child.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function centres(): BelongsToMany
    {
        return $this->belongsToMany(Centre::class, 'centre_child')
            ->using(CentreChild::class)
            ->withTimestamps();
    }

    /**
     * Get the invoice items associated with this child.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the enrolments associated with this child.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class);
    }

    /**
     * Get the active enrolments for this child.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activeEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class)->active();
    }

    /**
     * Get the current enrolments for this child.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function currentEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class)->current();
    }

    /**
     * Add this child to a centre.
     *
     * @param Centre|int $centre The centre or centre ID
     * @return void
     */
    public function addToCentre($centre): void
    {
        $centreId = $centre instanceof Centre ? $centre->id : $centre;

        if (!$this->centres()->where('centre_id', $centreId)->exists()) {
            $this->centres()->attach($centreId);
        }
    }

    /**
     * Remove this child from a centre.
     *
     * @param Centre|int $centre The centre or centre ID
     * @return void
     */
    public function removeFromCentre($centre): void
    {
        $centreId = $centre instanceof Centre ? $centre->id : $centre;

        $this->centres()->detach($centreId);
    }

    /**
     * Check if this child is associated with a specific centre.
     *
     * @param Centre|int $centre The centre or centre ID
     * @return bool
     */
    public function isInCentre($centre): bool
    {
        $centreId = $centre instanceof Centre ? $centre->id : $centre;

        return $this->centres()->where('centre_id', $centreId)->exists();
    }

    /**
     * Register media collections for the child.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('private');

        $this->addMediaCollection('birth_certificate')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->singleFile()
            ->useDisk('private');

        $this->addMediaCollection('immunization_card')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->singleFile()
            ->useDisk('private');
    }

    /**
     * Register media conversions for the child.
     */
    public function registerMediaConversions(Media $media = null): void
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
            ->performOnCollections('birth_certificate', 'immunization_card');
    }
}
