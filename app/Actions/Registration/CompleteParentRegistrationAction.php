<?php

namespace App\Actions\Registration;

use App\Models\User;

class CompleteParentRegistrationAction
{
    /**
     * Execute the action to complete parent registration.
     */
    public function execute(User $user, array $validated): void
    {
        // Mark profile as completed
        $user->profile_completed = true;

        // Set registration step to 4 (completed)
        $user->registration_step = 4;

        // Clear registration token
        $user->clearRegistrationToken();

        // Store completion data
        $user->updateRegistrationData(4, [
            'tnc_accepted' => $validated['tnc_accepted'],
            'undertaking_accepted' => $validated['undertaking_accepted'],
            'completed_at' => now()->toDateTimeString(),
        ]);
    }
}
