<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChildPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any children.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users with roles can view children
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher', 'Parent']);
    }

    /**
     * Determine whether the user can view the child.
     */
    public function view(User $user, Child $child): bool
    {
        // Super Admin can view any child
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can view any child in their tenant
        if ($user->hasRole('Admin')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal and Teacher can view children in their centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Parents can only view their associated children
        if ($user->hasRole('Parent')) {
            return $this->isParentOfChild($user, $child);
        }

        return false;
    }

    /**
     * Determine whether the user can create a new child.
     */
    public function create(User $user): bool
    {
        // Super Admin, Admin, Principal and Teachers can create children
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher']);
    }

    /**
     * Determine whether the user can update the child.
     */
    public function update(User $user, Child $child): bool
    {
        // Super Admin can update any child
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can update any child in their tenant
        if ($user->hasRole('Admin')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal and Teacher can update children in their centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Parents can update limited information for their associated children
        if ($user->hasRole('Parent')) {
            return $this->isParentOfChild($user, $child);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the child.
     */
    public function delete(User $user, Child $child): bool
    {
        // Super Admin can delete any child
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can delete children in their tenant
        if ($user->hasRole('Admin')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal can delete children in their centres
        if ($user->hasRole('Principal')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Teachers and Parents cannot delete children
        return false;
    }

    /**
     * Determine whether the user can delete any children.
     */
    public function deleteAny(User $user): bool
    {
        // Only Super Admin, Admin, and Principal can bulk delete children
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can restore the child.
     */
    public function restore(User $user, Child $child): bool
    {
        // Only Super Admin and Admin can restore children
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can permanently delete the child.
     */
    public function forceDelete(User $user, Child $child): bool
    {
        // Only Super Admin can force delete children
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can change the status of a child.
     */
    public function changeStatus(User $user, Child $child): bool
    {
        // Super Admin can change any child's status
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin and Principal can change status for children in their tenant
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Teachers can change status for children in their centres with restrictions
        if ($user->hasRole('Teacher')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        return false;
    }

    /**
     * Determine whether the user can view all children (not restricted by centre).
     */
    public function viewAllChildren(User $user): bool
    {
        // Super Admin and Admin can view all children in their tenant
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can associate/disassociate parents with children.
     */
    public function manageParents(User $user, Child $child): bool
    {
        // Super Admin can manage any child's parents
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin and Principal can manage parents for children in their tenant
        if ($user->hasAnyRole(['Admin', 'Principal'])) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        return false;
    }

    /**
     * Helper method to check if a child belongs to the user's current tenant.
     */
    private function childBelongsToUserTenant(User $user, Child $child): bool
    {
        if (!$user->current_tenant_id) {
            return false;
        }

        return $child->tenants()->where('tenant_id', $user->current_tenant_id)->exists();
    }

    /**
     * Helper method to check if a user is a parent of the child.
     */
    private function isParentOfChild(User $user, Child $child): bool
    {
        return $child->users()->where('users.id', $user->id)->exists();
    }
}