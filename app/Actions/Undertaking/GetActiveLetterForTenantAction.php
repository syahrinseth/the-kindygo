<?php

namespace App\Actions\Undertaking;

use App\Models\LetterOfUndertaking;
use App\Models\Tenant;

class GetActiveLetterForTenantAction
{
    /**
     * Get the active letter of undertaking for a tenant.
     */
    public function execute(Tenant $tenant): ?LetterOfUndertaking
    {
        return $tenant->lettersOfUndertaking()
            ->where('is_active', true)
            ->first();
    }
}
