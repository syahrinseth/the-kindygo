<?php

namespace App\Filament\Forms;

use App\Models\User;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function make(bool $showRoleSelect = true): array
    {
        return [
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make('Role Assignment')
                ->schema([
                    Forms\Components\Select::make('roles')
                        ->multiple()
                        ->relationship('roles', 'name')
                        ->options(function () {
                            $user = Auth::user();
                            $allRoles = Role::all();
                            
                            // Super Admin can assign any role
                            if ($user->hasRole('Super Admin')) {
                                return $allRoles->pluck('name', 'id');
                            }
                            
                            // Admin can assign all roles except Super Admin
                            if ($user->hasRole('Admin')) {
                                return $allRoles->where('name', '!=', 'Super Admin')->pluck('name', 'id');
                            }
                            
                            // Principal can only assign Teacher and Parent roles
                            if ($user->hasRole('Principal')) {
                                return $allRoles->whereIn('name', ['Teacher', 'Parent'])->pluck('name', 'id');
                            }
                            
                            // Default fallback
                            return collect();
                        })
                        ->preload()
                        ->required()
                        ->disabled(function (?User $record = null) {
                            if (!$record) return false;
                            return !Auth::user()->can('manageRoles', $record);
                        })
                        ->afterStateHydrated(function ($component, ?User $record = null) {
                            // Set default role only for new users and if no selection exists
                            if (!$record && empty($component->getState())) {
                                $user = Auth::user();
                                
                                // If Principal is creating user, default to Parent
                                if ($user->hasRole('Principal')) {
                                    $parentRole = Role::where('name', 'Parent')->first();
                                    if ($parentRole) {
                                        $component->state([$parentRole->id]);
                                    }
                                }
                            }
                        })
                        ->visible($showRoleSelect),
                ])
                ->visible($showRoleSelect)
                ->collapsible(),
            
            Forms\Components\Section::make('Centre Assignments')
                ->schema([
                    Forms\Components\Select::make('centres')
                        ->multiple()
                        ->relationship('centres', 'name', function ($query) {
                            $user = Auth::user();
                            
                            // Super Admin and Admin can see all centres in tenant
                            if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
                                return $query;
                            }
                            
                            // Principal can only assign to their centres
                            if ($user->hasRole('Principal')) {
                                return $query->whereHas('users', function ($q) use ($user) {
                                    $q->where('users.id', $user->id);
                                });
                            }
                            
                            return $query->whereRaw('1 = 0'); // Empty result
                        })
                        ->preload()
                        ->searchable()
                        ->disabled(function (?User $record = null) {
                            if (!$record) return false;
                            return !Auth::user()->can('manageCentres', $record);
                        })
                ])
                ->visible(function (?User $record = null) {
                    if (!$record) return Auth::user()->can('manageCentres', new User());
                    return Auth::user()->can('manageCentres', $record);
                })
                ->afterStateUpdated(function ($component, $state) {
                    // Clear current centre if no centres are assigned
                    if (empty($state)) {
                        $user = $component->getRecord();
                        if ($user && $user->exists) {
                            $user->setCurrentCentre(null);
                        }
                    }
                })
                ->collapsible(),

            Forms\Components\Section::make('Password')
                ->schema([
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create')
                        ->confirmed()
                        ->minLength(8)
                        ->maxLength(255)
                        ->label(fn (string $context): string => 
                            $context === 'edit' ? 'New Password' : 'Password'
                        ),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->required(fn (string $context): bool => $context === 'create')
                        ->minLength(8)
                        ->maxLength(255)
                        ->label(fn (string $context): string => 
                            $context === 'edit' ? 'Confirm New Password' : 'Confirm Password'
                        )
                        ->dehydrated(false),
                ])
                ->columns(2)
                ->collapsible(),
        ];
    }
}
