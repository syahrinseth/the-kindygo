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
        return $user->hasAnyRole(['super-admin', 'admin', 'principal', 'teacher', 'parent']);
    }

    /**
     * Determine whether the user can view the child.
     */
    public function view(User $user, Child $child): bool
    {
        // Super Admin can view any child
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin can view any child in their tenant
        if ($user->hasRole('admin')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal and Teacher can view children in their centres
        if ($user->hasAnyRole(['principal', 'teacher'])) {
            return $this->canAccessChildBasedOnCentres($user, $child);
        }

        // Parents can only view their associated children
        if ($user->hasRole('parent')) {
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
        return $user->hasAnyRole(['super-admin', 'admin', 'principal', 'teacher']);
    }

    /**
     * Determine whether the user can update the child.
     */
    public function update(User $user, Child $child): bool
    {
        // Super Admin can update any child
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin can update any child in their tenant
        if ($user->hasRole('admin')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal and Teacher can update children in their centres
        if ($user->hasAnyRole(['principal', 'teacher'])) {
            return $this->canAccessChildBasedOnCentres($user, $child);
        }

        // Parents can update limited information for their associated children
        if ($user->hasRole('parent')) {
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
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin can delete children in their tenant
        if ($user->hasRole('admin')) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal can delete children in their centres
        if ($user->hasRole('principal')) {
            return $this->canAccessChildBasedOnCentres($user, $child);
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
        return $user->hasAnyRole(['super-admin', 'admin', 'principal']);
    }

    /**
     * Determine whether the user can restore the child.
     */
    public function restore(User $user, Child $child): bool
    {
        // Only Super Admin and Admin can restore children
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the child.
     */
    public function forceDelete(User $user, Child $child): bool
    {
        // Only Super Admin can force delete children
        return $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can change the status of a child.
     */
    public function changeStatus(User $user, Child $child): bool
    {
        // Super Admin can change any child's status
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin and Principal can change status for children in their tenant/centres
        if ($user->hasAnyRole(['admin', 'principal'])) {
            return $this->canAccessChildBasedOnCentres($user, $child);
        }

        // Teachers can change status for children in their centres with restrictions
        if ($user->hasRole('teacher')) {
            return $this->canAccessChildBasedOnCentres($user, $child);
        }

        return false;
    }

    /**
     * Determine whether the user can view all children (not restricted by centre).
     */
    public function viewAllChildren(User $user): bool
    {
        // Super Admin and Admin can view all children in their tenant
        return $user->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Determine whether the user can associate/disassociate parents with children.
     */
    public function manageParents(User $user, Child $child): bool
    {
        // Super Admin can manage any child's parents
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Admin and Principal can manage parents for children in their tenant/centres
        if ($user->hasAnyRole(['admin', 'principal'])) {
            return $this->canAccessChildBasedOnCentres($user, $child);
        }

        return false;
    }

    /**
     * Helper method to check if a child belongs to the user's current tenant.
     */
    private function childBelongsToUserTenant(User $user, Child $child): bool
    {
        if (! $user->current_tenant_id) {
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

    /**
     * Helper method to check if a child belongs to any of the user's centres.
     */
    private function childBelongsToUserCentres(User $user, Child $child): bool
    {
        if (! $user->current_tenant_id) {
            return false;
        }

        // Get the centres that the user has access to in the current tenant
        $userCentreIds = $user->centres()
            ->where('centres.tenant_id', $user->current_tenant_id)
            ->pluck('centres.id');

        if ($userCentreIds->isEmpty()) {
            return false;
        }

        // All enrolments, including historical enrolments, establish centre association.
        return $child->enrolments()
            ->whereIn('centre_id', $userCentreIds)
            ->exists();
    }

    /**
     * Helper method to check centre access based on user role.
     */
    private function canAccessChildBasedOnCentres(User $user, Child $child): bool
    {
        // Super Admin and Admin can access all children in their tenant (no centre restriction)
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $this->childBelongsToUserTenant($user, $child);
        }

        // Principal and Teacher can only access children in their assigned centres
        if ($user->hasAnyRole(['principal', 'teacher'])) {
            return $this->childBelongsToUserTenant($user, $child) &&
                   $this->childBelongsToUserCentres($user, $child);
        }

        return false;
    }
}
