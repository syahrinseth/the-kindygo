<?php

namespace App\Actions\Undertaking;

use App\Models\LetterOfUndertaking;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;

class CheckParentUndertakingAgreementAction
{
    /**
     * Check if parent has agreed to the active letter of undertaking.
     * Returns the pending letter if not agreed, otherwise null.
     */
    public function execute(User $user, Tenant $tenant): ?LetterOfUndertaking
    {
        // Check if tenant requires undertaking agreement
        if (! $tenant->require_undertaking_agreement) {
            return null;
        }

        // Get active letter
        $activeLetter = app(GetActiveLetterForTenantAction::class)->execute($tenant);

        // No active letter means no requirement
        if (! $activeLetter) {
            return null;
        }

        // Check if user has agreed to this specific letter
        $hasAgreed = $user->undertakingAgreements()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('letter_of_undertaking_id', $activeLetter->id)
            ->exists();

        return $hasAgreed ? null : $activeLetter;
    }
}
