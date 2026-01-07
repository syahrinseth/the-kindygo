<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitation as TenantInvitationNotification;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class InviteUserToTenantAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('invite-to-tenant')
            ->label('Invite User to Company')
            ->icon('heroicon-o-envelope')
            ->schema([
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->label('Role')
                    ->options(function () {
                        $user = Auth::user();
                        $allRoles = Role::all();

                        // Super Admin can assign any role
                        if ($user->hasRole('Super Admin')) {
                            return $allRoles->pluck('name', 'name');
                        }

                        // Admin can assign all roles except Super Admin
                        if ($user->hasRole('Admin')) {
                            return $allRoles->where('name', '!=', 'Super Admin')->pluck('name', 'name');
                        }

                        // Principal can only assign Teacher and Parent roles
                        if ($user->hasRole('Principal')) {
                            return $allRoles->whereIn('name', ['Teacher', 'Parent'])->pluck('name', 'name');
                        }

                        // Default fallback to Parent role only
                        return collect(['Parent' => 'Parent']);
                    })
                    ->default('Parent')
                    ->required()
            ])
            ->modalHeading('Invite User to a Company')
            ->action(function (array $data): void {
                DB::transaction(function () use ($data) {
                    $tenant = Auth::user()->currentTenant();

                    // Check if the user already exists
                    $existingUser = User::where('email', $data['email'])->first();

                    if ($existingUser && $tenant->hasUser($existingUser)) {
                        $this->halt('This user is already a member of this company.');
                    }

                    // Create invitation record
                    $invitation = $tenant->invitations()->create([
                        'email' => $data['email'],
                        'role' => $data['role'],
                        'token' => Str::random(32),
                        'expires_at' => now()->addDays(7),
                    ]);

                    // Send invitation email
                    if ($existingUser) {
                        $existingUser->notify(new TenantInvitationNotification($invitation));
                    } else {
                        Notification::route('mail', $data['email'])
                            ->notify(new TenantInvitationNotification($invitation));
                    }
                });
            })
            ->successNotificationTitle('User invitation sent successfully');
    }
}
