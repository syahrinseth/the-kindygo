<?php

namespace App\Observers;

use App\Models\ChildEnrolment;
use Illuminate\Support\Facades\Auth;

class ChildEnrolmentObserver
{
    public function creating(ChildEnrolment $enrolment): void
    {
        if (empty($enrolment->tenant_id)) {
            // Assign tenant_id before creating the enrolment
            $enrolment->tenant_id = Auth::user()?->currentTenant()?->id ?? 0;
        }
    }
}
