<?php

namespace App\Policies;

use App\Models\Centre;
use App\Models\User;

class CentrePolicy
{
    public function viewAny(User $user): bool
    {
        // Only Super Admin, Admin, Principal, and Teacher can view the list of centres
        // Parents cannot view the centres resource
        return $user->hasAnyRole(['super-admin', 'admin', 'principal', 'teacher']);
    }

    public function view(User $user, Centre $centre): bool
    {
        // Check role-based permissions first
        if (! $user->hasAnyRole(['super-admin', 'admin', 'principal', 'teacher'])) {
            return false;
        }

        // Check tenant-based permission
        return $centre->tenant_id === $user->current_tenant_id;
    }

    public function create(User $user): bool
    {
        // Only Super Admin and Admin can create centres
        // Principals cannot create new centres
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    public function update(User $user, Centre $centre): bool
    {
        // Super Admin and Admin can update any centre in their tenant
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $centre->tenant_id === $user->current_tenant_id;
        }

        // Principal can only update centres they are assigned to
        if ($user->hasRole('principal')) {
            return $centre->tenant_id === $user->current_tenant_id &&
                   $user->centres()->where('centres.id', $centre->id)->exists();
        }

        return false;
    }

    public function delete(User $user, Centre $centre): bool
    {
        // Only Super Admin and Admin can delete centres
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $centre->tenant_id === $user->current_tenant_id;
        }

        return false;
    }

    public function restore(User $user, Centre $centre): bool
    {
        // Only Super Admin and Admin can restore centres
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $centre->tenant_id === $user->current_tenant_id;
        }

        return false;
    }

    public function forceDelete(User $user, Centre $centre): bool
    {
        // Only Super Admin can force delete centres
        if ($user->hasRole('super-admin')) {
            return $centre->tenant_id === $user->current_tenant_id;
        }

        return false;
    }
}
