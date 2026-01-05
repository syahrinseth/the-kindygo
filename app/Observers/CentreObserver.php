<?php

namespace App\Observers;

use App\Models\Centre;
use Illuminate\Support\Facades\Auth;

class CentreObserver
{
    public function creating(Centre $centre): void
    {
        if (empty($centre->tenant_id)) {
            // Assign tenant_id before creating the centre
            $centre->tenant_id = Auth::user()?->currentTenant()?->id ?? 0;
        }
    }
}
