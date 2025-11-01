<?php

namespace App\Models;

use App\Models\User;
use App\Models\Child;
use App\Models\Invoice;
use App\Enums\ChildStatus;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'personal_tenant',
        'phone',
        'email',
        'address_1',
        'address_2',
        'postal_code',
        'city',
        'state',
        // Business Information for e-Invoice
        'tax_identification_number',
        'business_activity_code',
        'business_activity_description',
        'business_id_type',
        'business_id_value',
        'country',
        'state_code',
        // E-Invoice API Credentials
        'einvoice_client_id',
        'einvoice_client_secret',
        'einvoice_environment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_tenant' => 'boolean',
    ];

    /**
     * Get the user that owns the tenant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function centres(): HasMany
    {
        return $this->hasMany(Centre::class);
    }

    /**
     * Get the users belonging to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * Get all roles for this tenant.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Check if a user belongs to this tenant.
     */
    public function hasUser(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Add a user to this tenant.
     */
    public function addUser(User $user): void
    {
        if (!$this->hasUser($user)) {
            $this->users()->attach($user->id);
        }
    }

    /**
     * Remove a user from this tenant.
     */
    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Get the invoices belonging to the tenant.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the child enrolments belonging to the tenant.
     */
    public function childEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class);
    }

    /**
     * Get the active child enrolments belonging to the tenant.
     */
    public function activeChildEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class)->active();
    }

    /**
     * Get the current child enrolments belonging to the tenant.
     */
    public function currentChildEnrolments(): HasMany
    {
        return $this->hasMany(ChildEnrolment::class)->current();
    }

    /**
     * Get the tenant's pending invitations.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TenantInvitation::class);
    }

    /**
     * Get the children belonging to the tenant.
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Child::class, 'tenant_child')
            ->withPivot('status')
            ->withTimestamps()
            ->using(TenantChild::class);
    }

    /**
     * Add a child to this tenant with a specific status.
     *
     * @param Child|int $child The child or child ID
     * @param ChildStatus $status The initial status
     * @return void
     */
    public function addChild($child, ChildStatus $status = ChildStatus::NEW): void
    {
        $childId = $child instanceof Child ? $child->id : $child;

        if (!$this->hasChild($childId)) {
            $this->children()->attach($childId, [
                'status' => $status,
            ]);
        }
    }

    /**
     * Remove a child from this tenant.
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
     * Check if a child belongs to this tenant.
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
     * Get the status of a child at this tenant.
     *
     * @param Child|int $child The child or child ID
     * @return ChildStatus|null The status or null if the child is not associated with this tenant
     */
    public function getChildStatus($child): ?ChildStatus
    {
        $childId = $child instanceof Child ? $child->id : $child;

        $pivotData = $this->children()->where('child_id', $childId)->first()?->pivot;

        return $pivotData ? $pivotData->status : null;
    }

    /**
     * Update the status of a child at this tenant.
     *
     * @param Child|int $child The child or child ID
     * @param ChildStatus $status The new status
     * @return bool Whether the update was successful
     */
    public function updateChildStatus($child, ChildStatus $status): bool
    {
        $childId = $child instanceof Child ? $child->id : $child;

        $updated = $this->children()->updateExistingPivot($childId, [
            'status' => $status,
        ]);

        return $updated > 0;
    }

    /**
     * Get children with a specific status at this tenant.
     *
     * @param ChildStatus $status The status to filter by
     * @return \Illuminate\Database\Eloquent\Collection The children with the specified status
     */
    public function getChildrenByStatus(ChildStatus $status)
    {
        return $this->children()
            ->wherePivot('status', $status)
            ->get();
    }

    /**
     * Get new children at this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getNewChildren()
    {
        return $this->getChildrenByStatus(ChildStatus::NEW);
    }

    /**
     * Get active children at this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveChildren()
    {
        return $this->getChildrenByStatus(ChildStatus::ACTIVE);
    }

    /**
     * Get returning children at this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getReturningChildren()
    {
        return $this->getChildrenByStatus(ChildStatus::RETURN);
    }

    /**
     * Get alumni children at this tenant.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAlumniChildren()
    {
        return $this->getChildrenByStatus(ChildStatus::ALUMNI);
    }

    /**
     * Create a personal tenant for a user.
     */
    public static function createPersonalTenant(User $user): self
    {
        $tenant = static::create([
            'name' => "{$user->name}'s Space",
            'slug' => str($user->name)->slug() . '-space',
            'user_id' => $user->id,
            'personal_tenant' => true,
        ]);

        $tenant->addUser($user);
        $user->setCurrentTenant($tenant);

        return $tenant;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the e-Invoice client ID for this tenant.
     * Falls back to global config if not set.
     *
     * @return string|null
     */
    public function getEInvoiceClientId(): ?string
    {
        return $this->einvoice_client_id ?? config('einvoice.client_id');
    }

    /**
     * Get the e-Invoice client secret for this tenant.
     * Falls back to global config if not set.
     *
     * @return string|null
     */
    public function getEInvoiceClientSecret(): ?string
    {
        return $this->einvoice_client_secret ?? config('einvoice.client_secret');
    }

    /**
     * Get the e-Invoice environment for this tenant.
     * Falls back to global config if not set.
     *
     * @return string
     */
    public function getEInvoiceEnvironment(): string
    {
        return $this->einvoice_environment ?? config('einvoice.environment', 'sandbox');
    }

    /**
     * Check if tenant has e-Invoice credentials configured.
     *
     * @return bool
     */
    public function hasEInvoiceCredentials(): bool
    {
        return !empty($this->einvoice_client_id) && !empty($this->einvoice_client_secret);
    }

    /**
     * Check if tenant is using production e-Invoice environment.
     *
     * @return bool
     */
    public function isEInvoiceProduction(): bool
    {
        return $this->getEInvoiceEnvironment() === 'production';
    }
}
