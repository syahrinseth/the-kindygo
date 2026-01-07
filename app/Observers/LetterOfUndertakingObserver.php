<?php

namespace App\Observers;

use App\Models\LetterOfUndertaking;
use App\Models\Scopes\TenantScope;

class LetterOfUndertakingObserver
{
    /**
     * Handle the LetterOfUndertaking "creating" event.
     */
    public function creating(LetterOfUndertaking $letterOfUndertaking): void
    {
        // Auto-increment version based on tenant's last letter version
        if (! $letterOfUndertaking->version) {
            $lastVersion = LetterOfUndertaking::withoutGlobalScope(TenantScope::class)
                ->forTenant($letterOfUndertaking->tenant_id)
                ->max('version') ?? 0;

            $letterOfUndertaking->version = $lastVersion + 1;
        }
    }
}
