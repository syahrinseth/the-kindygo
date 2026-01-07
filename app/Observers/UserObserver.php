<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Str;

class UserObserver
{
    public function creating(User $user): void
    {
        // Only set registration token for new users without one
        if (empty($user->registration_token)) {
            $user->registration_token = Str::random(40);
            $user->registration_token_expires_at = now()->addDays(30);
        }
    }
}
