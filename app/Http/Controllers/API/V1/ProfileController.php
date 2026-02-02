<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\UpdateProfileRequest;
use App\Http\Resources\API\V1\UserResource;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Profile
 */
#[Group('Profile', 'Endpoints for managing user profile information.')]
class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     *
     * Returns the current user's profile including personal details, address, and tenant memberships.
     */
    #[Endpoint(operationId: 'profile.show', title: 'Get profile')]
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->load(['profile', 'userAddress', 'tenants']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * Updates the user's profile information including name, personal details, and address.
     */
    #[Endpoint(operationId: 'profile.update', title: 'Update profile')]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validated();

        // Update user basic info
        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        // Update profile
        if (isset($validated['profile'])) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $validated['profile']
            );
        }

        // Update address
        if (isset($validated['address'])) {
            $user->userAddress()->updateOrCreate(
                ['user_id' => $user->id],
                $validated['address']
            );
        }

        $user->load(['profile', 'userAddress']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Upload profile photo.
     *
     * Uploads a new profile photo for the authenticated user. The previous photo will be replaced.
     * Maximum file size is 5MB. Supported formats: JPEG, PNG, GIF.
     *
     * @requestMediaType multipart/form-data
     */
    #[Endpoint(operationId: 'profile.uploadPhoto', title: 'Upload profile photo')]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Remove existing photo
        $user->clearMediaCollection('photo');

        // Add new photo
        $user->addMediaFromRequest('photo')
            ->toMediaCollection('photo');

        return response()->json([
            'success' => true,
            'message' => 'Profile photo uploaded successfully.',
            'data' => [
                'photo_url' => $user->getFilamentAvatarUrl(),
            ],
        ]);
    }

    /**
     * Delete profile photo.
     *
     * Removes the authenticated user's profile photo. The default avatar will be used instead.
     */
    #[Endpoint(operationId: 'profile.deletePhoto', title: 'Delete profile photo')]
    public function deletePhoto(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->clearMediaCollection('photo');

        return response()->json([
            'success' => true,
            'message' => 'Profile photo deleted successfully.',
        ]);
    }
}
