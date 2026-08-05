<?php

namespace App\Filament\Admin\Resources\Users\Actions;

use App\Enums\ApplicationRole;
use App\Models\User;
use App\Notifications\TenantInvitation as TenantInvitationNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                        $allRoles = Role::query()
                            ->whereIn('name', array_keys(ApplicationRole::options()))
                            ->get();

                        $roleOptions = $allRoles->mapWithKeys(fn (Role $role): array => [
                            $role->name => ApplicationRole::labelFor($role->name),
                        ]);

                        // Super Admin can assign any role
                        if ($user->hasRole('super-admin')) {
                            return $roleOptions;
                        }

                        // Admin can assign all roles except Super Admin
                        if ($user->hasRole('admin')) {
                            return $allRoles
                                ->where('name', '!=', 'super-admin')
                                ->mapWithKeys(fn (Role $role): array => [
                                    $role->name => ApplicationRole::labelFor($role->name),
                                ]);
                        }

                        // Principal can only assign Teacher and Parent roles
                        if ($user->hasRole('principal')) {
                            return $allRoles
                                ->whereIn('name', ['teacher', 'parent'])
                                ->mapWithKeys(fn (Role $role): array => [
                                    $role->name => ApplicationRole::labelFor($role->name),
                                ]);
                        }

                        // Default fallback to Parent role only
                        return collect(['parent' => 'Parent']);
                    })
                    ->default('parent')
                    ->required(),
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
