<?php

namespace App\Filament\Resources\UserResource\Actions;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use App\Notifications\TenantInvitation as TenantInvitationNotification;

class InviteUserToTenantAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('invite-to-tenant')
            ->label('Invite User to Company')
            ->icon('heroicon-o-envelope')
            ->form([
                Select::make('tenant_id')
                    ->label('Company')
                    ->options(fn () => Tenant::where('user_id', Auth::id())
                        ->orWhereHas('users', fn ($query) => $query->where('users.id', Auth::id()))
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required(),
                Select::make('role')
                    ->label('Role')
                    ->options(fn () => Role::pluck('name', 'name'))
                    ->default('user')
                    ->required()
            ])
            ->modalHeading('Invite User to a Company')
            ->action(function (array $data): void {
                DB::transaction(function () use ($data) {
                    $tenant = Tenant::findOrFail($data['tenant_id']);
                    
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
