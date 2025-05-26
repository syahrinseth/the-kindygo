<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        // Super Admin and Admin can view all users
        // Principals can view users in their tenant
        // Teachers and Parents cannot view user management
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Super Admin can view any user
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can view any user in the same tenant
        if ($user->hasRole('Admin')) {
            return $this->sharesTenant($user, $model);
        }

        // Principal can view users in their centres
        if ($user->hasRole('Principal')) {
            return $this->sharesCurrentCentre($user, $model);
        }

        return false;
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        // Super Admin, Admin, and Principal can create users
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile (limited fields)
        if ($user->id === $model->id) {
            return true;
        }

        // Super Admin can update any user
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can update users in the same tenant
        if ($user->hasRole('Admin')) {
            return $this->sharesTenant($user, $model);
        }

        // Principal can update users in their centres
        if ($user->hasRole('Principal')) {
            return $this->sharesCurrentCentre($user, $model);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Users cannot delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        // Super Admin can delete any user except themselves
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can delete users in the same tenant (except Super Admins)
        if ($user->hasRole('Admin') && !$model->hasRole('Super Admin')) {
            return $this->sharesTenant($user, $model);
        }

        // Principal can delete Teachers and Parents in their centres
        if ($user->hasRole('Principal') && $model->hasAnyRole(['Teacher', 'Parent'])) {
            return $this->sharesCurrentCentre($user, $model);
        }

        return false;
    }

    /**
     * Determine whether the user can delete any users.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Only Super Admin can restore users
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only Super Admin can force delete users
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can access the Filament admin panel.
     */
    public function accessPanel(User $user): bool
    {
        // All roles except Parent can access the admin panel
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Teacher', 'Parent']);
    }

    /**
     * Determine whether the user can manage roles for other users.
     */
    public function manageRoles(User $user, User $model): bool
    {
        // Users cannot manage their own roles
        if ($user->id === $model->id) {
            return false;
        }

        // Super Admin can manage any roles
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Admin can manage roles for non-Super Admin users in same tenant
        if ($user->hasRole('Admin') && !$model->hasRole('Super Admin')) {
            return $this->sharesTenant($user, $model);
        }

        // Principal can only assign Teacher and Parent roles
        if ($user->hasRole('Principal')) {
            return $this->sharesCurrentCentre($user, $model) && 
                   $model->hasAnyRole(['Teacher', 'Parent']);
        }

        return false;
    }

    /**
     * Determine whether the user can manage centre assignments.
     */
    public function manageCentres(User $user, User $model): bool
    {
        // Super Admin and Admin can manage centre assignments
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return true;
        }

        // Principal can manage centre assignments for their centres
        if ($user->hasRole('Principal')) {
            return $this->sharesCurrentCentre($user, $model);
        }

        return false;
    }

    /**
     * Determine whether the user can invite new users to tenant.
     */
    public function inviteUsers(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view all users (not restricted by centre).
     */
    public function viewAllUsers(User $user): bool
    {
        // Super Admin and Admin can view all users in their context
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can assign higher-level roles.
     */
    public function assignHigherRoles(User $user): bool
    {
        // Only Super Admin can assign Super Admin and Admin roles
        // Admin can assign Principal, Teacher, Parent roles
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can bulk edit users.
     */
    public function bulkEdit(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Helper method to check if two users share the same tenant.
     */
    private function sharesTenant(User $user1, User $user2): bool
    {
        if (!$user1->current_tenant_id || !$user2->current_tenant_id) {
            return false;
        }

        return $user1->current_tenant_id === $user2->current_tenant_id;
    }

    /**
     * Helper method to check if two users share the same current centre.
     */
    private function sharesCurrentCentre(User $user1, User $user2): bool
    {
        $user1Centre = $user1->currentCentre;
        $user2Centre = $user2->currentCentre;

        if (!$user1Centre || !$user2Centre) {
            return false;
        }

        return $user1Centre->id === $user2Centre->id;
    }

    /**
     * Helper method to check if a user has access to a specific centre.
     */
    private function hasAccessToCentre(User $user, int $centreId): bool
    {
        return $user->centres()->where('centres.id', $centreId)->exists();
    }
}
