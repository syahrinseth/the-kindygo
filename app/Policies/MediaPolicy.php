<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the media.
     */
    public function view(User $user, Media $media): bool
    {
        // Get the model that owns this media
        $model = $media->model;

        // If the media doesn't have an associated model, deny access
        if (! $model) {
            return false;
        }

        // Check based on the model type
        switch (get_class($model)) {
            case 'App\Models\Child':
                return $this->canViewChildMedia($user, $model);
            default:
                return false;
        }
    }

    /**
     * Determine if user can view child media.
     */
    private function canViewChildMedia(User $user, $child): bool
    {
        // Admin users can view all media
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Check if user is associated with this child
        if ($child->users()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Check if user has access through tenants
        $userTenantIds = $user->tenants()->pluck('tenant_id')->toArray();
        $childTenantIds = $child->tenants()->pluck('tenant_id')->toArray();

        return ! empty(array_intersect($userTenantIds, $childTenantIds));
    }
}
