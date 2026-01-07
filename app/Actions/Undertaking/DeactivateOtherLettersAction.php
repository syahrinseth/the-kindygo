<?php

namespace App\Actions\Undertaking;

use App\Models\LetterOfUndertaking;
use App\Models\Scopes\TenantScope;

class DeactivateOtherLettersAction
{
    /**
     * Deactivate all other letters of undertaking for the same tenant.
     */
    public function execute(LetterOfUndertaking $letter): void
    {
        LetterOfUndertaking::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $letter->tenant_id)
            ->where('id', '!=', $letter->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
