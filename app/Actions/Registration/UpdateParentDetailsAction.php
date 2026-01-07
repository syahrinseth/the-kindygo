<?php

namespace App\Actions\Registration;

use App\Models\User;

class UpdateParentDetailsAction
{
    /**
     * Execute the action to update parent details.
     */
    public function execute(User $user, array $validated): void
    {
        // Create or update UserProfile
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'occupation' => $validated['occupation'] ?? null,
            ]
        );

        // Create or update UserAddress
        $user->userAddress()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'address' => $validated['address'],
                'address_2' => $validated['address_2'] ?? null,
                'postal_code' => $validated['postal_code'],
                'city' => $validated['city'],
                'state_code' => $validated['state'],
            ]
        );

        // Create or update UserOfficeInfo if any office data provided
        if (!empty($validated['office_address'])) {
            $user->officeInfo()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'office_address' => $validated['office_address'] ?? null,
                    'office_address_2' => $validated['office_address_2'] ?? null,
                    'office_postal_code' => $validated['office_postal_code'] ?? null,
                    'office_city' => $validated['office_city'] ?? null,
                    'office_state_code' => $validated['office_state'] ?? null,
                ]
            );
        }

        // Handle optional file uploads with Spatie Media Library
        if (isset($validated['profile_photo'])) {
            $user->clearMediaCollection('photo');
            $user->addMedia($validated['profile_photo'])
                ->toMediaCollection('photo');
        }

        if (isset($validated['mykad_image'])) {
            $user->clearMediaCollection('mykad');
            $user->addMedia($validated['mykad_image'])
                ->toMediaCollection('mykad');
        }

        if (isset($validated['immunization_card'])) {
            $user->clearMediaCollection('immunization');
            $user->addMedia($validated['immunization_card'])
                ->toMediaCollection('immunization');
        }

        // Update registration progress
        $user->registration_step = 2;
        $user->updateRegistrationData(2, [
            'occupation' => $validated['occupation'] ?? null,
            'address' => $validated['address'],
            'address_2' => $validated['address_2'] ?? null,
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'office_address' => $validated['office_address'] ?? null,
            'office_address_2' => $validated['office_address_2'] ?? null,
            'office_postal_code' => $validated['office_postal_code'] ?? null,
            'office_city' => $validated['office_city'] ?? null,
            'office_state' => $validated['office_state'] ?? null,
            'has_profile_photo' => isset($validated['profile_photo']),
            'has_mykad_image' => isset($validated['mykad_image']),
            'has_immunization_card' => isset($validated['immunization_card']),
        ]);
    }
}
