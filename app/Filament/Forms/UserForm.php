<?php

namespace App\Filament\Forms;

use Filament\Forms;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function make(bool $showRoleSelect = true): array
    {
        return [
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    $showRoleSelect ? 
                        Forms\Components\Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->required() :
                        Forms\Components\Select::make('roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->afterStateHydrated(function ($component) {
                                // Set default role only if no selection exists
                                if (empty($component->getState())) {
                                    // Find the Parent role ID
                                    $parentRole = \Spatie\Permission\Models\Role::where('name', 'Parent')->first();
                                    if ($parentRole) {
                                        $component->state([$parentRole->id]);
                                    }
                                }
                            })
                            ->required(),
                ])->columns(2),
            
            Forms\Components\Section::make('Centre Assignments')
                ->schema([
                    Forms\Components\Select::make('centres')
                        ->multiple()
                        ->relationship('centres', 'name')
                        ->preload()
                        ->searchable()
                ])
                ->columns(1)
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
