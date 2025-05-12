<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

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

    /**
     * Get the users belonging to the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
}
