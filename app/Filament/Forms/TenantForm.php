<?php

namespace App\Filament\Forms;

use Filament\Forms;
use Illuminate\Support\Str;

class TenantForm
{
    public static function make(): array
    {
        return [
            Forms\Components\Section::make()
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('slug', Str::slug($state));
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique('tenants', 'slug', ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->maxLength(20),
                ])->columns(2),

            Forms\Components\Section::make('Address')
                ->schema([
                    Forms\Components\TextInput::make('address_1')
                        ->label('Address Line 1')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('address_2')
                        ->label('Address Line 2')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('city')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('state')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('postal_code')
                        ->required()
                        ->maxLength(20),
                ])
                ->columns(2)
                ->collapsible(),
        ];
    }
}
