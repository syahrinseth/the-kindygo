<?php

namespace App\Http\Controllers;

use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class TenantInvitationController extends Controller
{
    public function accept(string $token)
    {
        $invitation = TenantInvitation::where('token', $token)
            ->where('accepted_at', null)
            ->firstOrFail();

        if ($invitation->isExpired()) {
            return redirect()->route('login')
                ->with('error', 'This invitation has expired.');
        }

        // If user is not logged in and the invited email exists
        if (!Auth::check()) {
            $user = User::where('email', $invitation->email)->first();
            $invitationUrl = route('tenant-invitations.accept', $token);
            
            // if ($user) {
                return redirect()->route('login', ['redirect' => $invitationUrl])
                    ->with('info', 'Please log in to accept the invitation.')
                    ->withInput(['email' => $invitation->email]);
            // }

            // New user needs to register
            // return redirect()->route('register', ['redirect' => $invitationUrl])
            //     ->with('info', 'Please register to accept the invitation.')
            //     ->with('invitation_token', $token)
            //     ->withInput(['email' => $invitation->email]);
        }

        $user = Auth::user();

        // Verify the logged-in user's email matches the invitation
        if ($user->email !== $invitation->email) {
            $invitationUrl = route('tenant-invitations.accept', $token);
            Auth::logout();
            return redirect()->route('login', ['redirect' => $invitationUrl])
                ->with('error', 'Please log in with the invited email address.')
                ->withInput(['email' => $invitation->email]);
        }

        DB::transaction(function () use ($invitation, $user) {
            // Add user to tenant
            $invitation->tenant->addUser($user);
            
            // Assign role
            $user->assignRole($invitation->role);
            
            // Mark invitation as accepted
            $invitation->accept();
        });

        return redirect()->route('filament.app.tenant')
            ->with('success', "You've been added to {$invitation->tenant->name}.");
    }
}
