<?php

namespace App\Actions\Undertaking;

use App\Models\LetterOfUndertaking;
use App\Models\User;
use App\Notifications\NewLetterOfUndertakingNotification;
use Illuminate\Support\Facades\Notification;

class NotifyParentsOfNewLetterAction
{
    /**
     * Notify all parents of a tenant about a new active letter of undertaking.
     */
    public function execute(LetterOfUndertaking $letter): void
    {
        // Get all users with Parent role belonging to this tenant
        $parents = User::role('parent')
            ->whereHas('tenants', function ($query) use ($letter) {
                $query->where('tenant_id', $letter->tenant_id);
            })
            ->get();

        // Send notification to each parent
        Notification::send($parents, new NewLetterOfUndertakingNotification($letter));
    }
}
