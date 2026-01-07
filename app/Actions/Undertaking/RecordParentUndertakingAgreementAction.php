<?php

namespace App\Actions\Undertaking;

use App\Models\LetterOfUndertaking;
use App\Models\ParentUndertakingAgreement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class RecordParentUndertakingAgreementAction
{
    /**
     * Record that a parent has agreed to a letter of undertaking.
     */
    public function execute(User $user, LetterOfUndertaking $letter, Tenant $tenant, Request $request): ParentUndertakingAgreement
    {
        return ParentUndertakingAgreement::create([
            'user_id' => $user->id,
            'letter_of_undertaking_id' => $letter->id,
            'tenant_id' => $tenant->id,
            'agreed_at' => now(),
            'ip_address' => $request->ip(),
        ]);
    }
}
