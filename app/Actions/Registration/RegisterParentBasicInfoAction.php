<?php

namespace App\Actions\Registration;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterParentBasicInfoAction
{
    /**
     * Execute the action to register a parent with basic information.
     *
     * @param  array  $validated  The validated data
     * @param  Tenant  $tenant  The tenant to register with
     * @param  User|null  $existingUser  An existing user to update (if any)
     * @return array ['user' => User, 'shouldLogin' => bool]
     */
    public function execute(array $validated, Tenant $tenant, ?User $existingUser = null): array
    {
        // If an existing user is provided, update their information
        if ($existingUser) {
            $user = $existingUser;

            // Update user information
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            // Update password only if provided
            if (! empty($validated['password'])) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            $shouldLogin = false;
        } else {
            // Create new user for guests
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Assign Parent role to newly created user
            $user->assignRole('Parent');

            $shouldLogin = true;
        }

        // Set current_tenant_id if not set
        if (! $user->current_tenant_id) {
            $user->current_tenant_id = $tenant->id;
            $user->save();
        }

        // Create or update UserProfile with MyKad number
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nric' => $validated['mykad_number'] ?? null,
                'phone' => $validated['phone'],
            ]
        );

        // Create or update TenantUser relationship
        $user->tenants()->syncWithoutDetaching([
            $tenant->id => ['current_centre_id' => null],
        ]);

        // Sync selected centres
        $user->centres()->syncWithoutDetaching($validated['centre_ids']);

        // Update registration progress
        $user->registration_step = 1;
        $user->updateRegistrationData(1, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mykad_number' => $validated['mykad_number'] ?? null,
            'phone' => $validated['phone'],
            'centre_ids' => $validated['centre_ids'],
            'tenant_id' => $tenant->id,
        ]);

        return [
            'user' => $user,
            'shouldLogin' => $shouldLogin,
        ];
    }
}
