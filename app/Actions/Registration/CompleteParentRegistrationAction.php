<?php

namespace App\Actions\Registration;

use App\Actions\Undertaking\GetActiveLetterForTenantAction;
use App\Actions\Undertaking\RecordParentUndertakingAgreementAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class CompleteParentRegistrationAction
{
    /**
     * Execute the action to complete parent registration.
     */
    public function execute(User $user, array $validated, ?Request $request = null): void
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

        // Record undertaking agreement if accepted and there's an active letter
        if ($validated['undertaking_accepted']) {
            $tenant = $user->currentTenant();
            $activeLetter = app(GetActiveLetterForTenantAction::class)->execute($tenant);

            if ($activeLetter) {
                app(RecordParentUndertakingAgreementAction::class)->execute(
                    $user,
                    $activeLetter,
                    $tenant,
                    $request
                );
            }
        }
    }
}
