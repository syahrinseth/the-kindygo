<?php

namespace App\Policies;

use App\Models\ChildEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChildEnrollmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any child enrollments.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users with roles can view child enrollments
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher', 'Parent']);
    }

    /**
     * Determine whether the user can view the child enrollment.
     */
    public function view(User $user, ChildEnrollment $childEnrollment): bool
    {
        // Super Admin can view any child enrollment
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can view any child enrollment in their tenant
        if ($user->hasRole('Admin')) {
            return $this->enrollmentBelongsToUserTenant($user, $childEnrollment);
        }

        // Principal and Teacher can view enrollments for children in their centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->canAccessEnrollmentBasedOnCentres($user, $childEnrollment);
        }

        // Parents can only view enrollments for their associated children
        if ($user->hasRole('Parent')) {
            return $this->isParentOfEnrolledChild($user, $childEnrollment);
        }

        return false;
    }

    /**
     * Determine whether the user can create a new child enrollment.
     */
    public function create(User $user): bool
    {
        // Super Admin, Admin, Principal and Teachers can create child enrollments
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher']);
    }

    /**
     * Determine whether the user can update the child enrollment.
     */
    public function update(User $user, ChildEnrollment $childEnrollment): bool
    {
        // Super Admin can update any child enrollment
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can update any child enrollment in their tenant
        if ($user->hasRole('Admin')) {
            return $this->enrollmentBelongsToUserTenant($user, $childEnrollment);
        }

        // Principal and Teacher can update enrollments for children in their centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->canAccessEnrollmentBasedOnCentres($user, $childEnrollment);
        }

        // Parents cannot update enrollments (they should contact admin/teachers)
        return false;
    }

    /**
     * Determine whether the user can delete the child enrollment.
     */
    public function delete(User $user, ChildEnrollment $childEnrollment): bool
    {
        // Super Admin can delete any child enrollment
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can delete child enrollments in their tenant
        if ($user->hasRole('Admin')) {
            return $this->enrollmentBelongsToUserTenant($user, $childEnrollment);
        }

        // Principal can delete enrollments for children in their centres
        if ($user->hasRole('Principal')) {
            return $this->canAccessEnrollmentBasedOnCentres($user, $childEnrollment);
        }

        // Teachers and Parents cannot delete enrollments
        return false;
    }

    /**
     * Determine whether the user can restore the child enrollment.
     */
    public function restore(User $user, ChildEnrollment $childEnrollment): bool
    {
        // Only Super Admin and Admin can restore child enrollments
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($user->hasRole('Admin')) {
            return $this->enrollmentBelongsToUserTenant($user, $childEnrollment);
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the child enrollment.
     */
    public function forceDelete(User $user, ChildEnrollment $childEnrollment): bool
    {
        // Only Super Admin can permanently delete child enrollments
        return $user->hasRole('Super Admin');
    }

    /**
     * Helper method to check if a child enrollment belongs to the user's current tenant.
     */
    private function enrollmentBelongsToUserTenant(User $user, ChildEnrollment $childEnrollment): bool
    {
        if (!$user->current_tenant_id) {
            return false;
        }

        return $childEnrollment->tenant_id === $user->current_tenant_id;
    }

    /**
     * Helper method to check if a user is a parent of the enrolled child.
     */
    private function isParentOfEnrolledChild(User $user, ChildEnrollment $childEnrollment): bool
    {
        return $childEnrollment->child->users()->where('users.id', $user->id)->exists();
    }
    
    /**
     * Helper method to check if a child enrollment belongs to any of the user's centres.
     */
    private function enrollmentBelongsToUserCentres(User $user, ChildEnrollment $childEnrollment): bool
    {
        if (!$user->current_tenant_id) {
            return false;
        }

        // Get the centres that the user has access to in the current tenant
        $userCentreIds = $user->centres()
            ->where('centres.tenant_id', $user->current_tenant_id)
            ->pluck('centres.id');

        if ($userCentreIds->isEmpty()) {
            return false;
        }

        // Check if the enrollment is for a centre the user has access to
        return $userCentreIds->contains($childEnrollment->centre_id);
    }
    
    /**
     * Helper method to check centre access based on user role.
     */
    private function canAccessEnrollmentBasedOnCentres(User $user, ChildEnrollment $childEnrollment): bool
    {
        // Super Admin and Admin can access all enrollments in their tenant (no centre restriction)
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $this->enrollmentBelongsToUserTenant($user, $childEnrollment);
        }

        // Principal and Teacher can only access enrollments in their assigned centres
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            return $this->enrollmentBelongsToUserTenant($user, $childEnrollment) && 
                   $this->enrollmentBelongsToUserCentres($user, $childEnrollment);
        }

        return false;
    }
}
