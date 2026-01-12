<?php

namespace App\Policies;

use App\Models\ChildEnrolment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChildEnrolmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any child enrolments.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users with roles can view child enrolments
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher', 'Parent']);
    }

    /**
     * Determine whether the user can view the child enrolment.
     */
    public function view(User $user, ChildEnrolment $childEnrolment): bool
    {
        // Super Admin can view any child enrolment
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can view any child enrolment in their tenant
        if ($user->hasRole('Admin')) {
            return $this->enrolmentBelongsToUserTenant($user, $childEnrolment);
        }

        // Principal and Teacher can view enrolments for children in their centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->canAccessEnrolmentBasedOnCentres($user, $childEnrolment);
        }

        // Parents can only view enrolments for their associated children
        if ($user->hasRole('Parent')) {
            return $this->isParentOfEnrolledChild($user, $childEnrolment);
        }

        return false;
    }

    /**
     * Determine whether the user can create a new child enrolment.
     */
    public function create(User $user): bool
    {
        // Super Admin, Admin, Principal and Teachers can create child enrolments
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher']);
    }

    /**
     * Determine whether the user can update the child enrolment.
     */
    public function update(User $user, ChildEnrolment $childEnrolment): bool
    {
        // Super Admin can update any child enrolment
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can update any child enrolment in their tenant
        if ($user->hasRole('Admin')) {
            return $this->enrolmentBelongsToUserTenant($user, $childEnrolment);
        }

        // Principal and Teacher can update enrolments for children in their centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->canAccessEnrolmentBasedOnCentres($user, $childEnrolment);
        }

        // Parents cannot update enrolments (they should contact admin/teachers)
        return false;
    }

    /**
     * Determine whether the user can delete the child enrolment.
     */
    public function delete(User $user, ChildEnrolment $childEnrolment): bool
    {
        // Super Admin can delete any child enrolment
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can delete child enrolments in their tenant
        if ($user->hasRole('Admin')) {
            return $this->enrolmentBelongsToUserTenant($user, $childEnrolment);
        }

        // Principal can delete enrolments for children in their centres
        if ($user->hasRole('Principal')) {
            return $this->canAccessEnrolmentBasedOnCentres($user, $childEnrolment);
        }

        // Teachers and Parents cannot delete enrolments
        return false;
    }

    /**
     * Determine whether the user can restore the child enrolment.
     */
    public function restore(User $user, ChildEnrolment $childEnrolment): bool
    {
        // Only Super Admin and Admin can restore child enrolments
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($user->hasRole('Admin')) {
            return $this->enrolmentBelongsToUserTenant($user, $childEnrolment);
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the child enrolment.
     */
    public function forceDelete(User $user, ChildEnrolment $childEnrolment): bool
    {
        // Only Super Admin can permanently delete child enrolments
        return $user->hasRole('Super Admin');
    }

    /**
     * Helper method to check if a child enrolment belongs to the user's current tenant.
     */
    private function enrolmentBelongsToUserTenant(User $user, ChildEnrolment $childEnrolment): bool
    {
        if (! $user->current_tenant_id) {
            return false;
        }

        return $childEnrolment->tenant_id === $user->current_tenant_id;
    }

    /**
     * Helper method to check if a user is a parent of the enrolled child.
     */
    private function isParentOfEnrolledChild(User $user, ChildEnrolment $childEnrolment): bool
    {
        return $childEnrolment->child->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Helper method to check if a child enrolment belongs to any of the user's centres.
     */
    private function enrolmentBelongsToUserCentres(User $user, ChildEnrolment $childEnrolment): bool
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

        // Check if the enrolment is for a centre the user has access to
        return $userCentreIds->contains($childEnrolment->centre_id);
    }

    /**
     * Helper method to check centre access based on user role.
     */
    private function canAccessEnrolmentBasedOnCentres(User $user, ChildEnrolment $childEnrolment): bool
    {
        // Super Admin and Admin can access all enrolments in their tenant (no centre restriction)
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $this->enrolmentBelongsToUserTenant($user, $childEnrolment);
        }

        // Principal and Teacher can only access enrolments in their assigned centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->enrolmentBelongsToUserTenant($user, $childEnrolment) &&
                $this->enrolmentBelongsToUserCentres($user, $childEnrolment);
        }

        return false;
    }
}
