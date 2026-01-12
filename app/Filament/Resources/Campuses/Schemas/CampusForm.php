<?php

namespace App\Filament\Resources\Campuses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CampusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campus Information')
                    ->description('Enter the campus name and contact details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Campus Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Kuala Lumpur Campus')
                            ->suffixIcon('heroicon-m-building-office-2')
                            ->columnSpanFull()
                            ->autofocus(),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('+60 3-1234 5678')
                            ->suffixIcon('heroicon-m-phone'),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('campus@example.com')
                            ->suffixIcon('heroicon-m-envelope'),
                    ])
                    ->columns(2),

                Section::make('Location')
                    ->description('Provide the complete campus address')
                    ->schema([
                        TextInput::make('address_1')
                            ->label('Address Line 1')
                            ->maxLength(255)
                            ->placeholder('123 Jalan Example')
                            ->columnSpanFull(),

                        TextInput::make('address_2')
                            ->label('Address Line 2')
                            ->maxLength(255)
                            ->placeholder('Taman Test (optional)')
                            ->helperText('Additional address details if needed')
                            ->columnSpanFull(),

                        TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(5)
                            ->placeholder('50000')
                            ->mask('99999')
                            ->suffixIcon('heroicon-m-hashtag'),

                        TextInput::make('city')
                            ->label('City')
                            ->maxLength(255)
                            ->placeholder('Kuala Lumpur')
                            ->suffixIcon('heroicon-m-map-pin'),

                        TextInput::make('state')
                            ->label('State')
                            ->maxLength(255)
                            ->placeholder('Selangor')
                            ->suffixIcon('heroicon-m-map'),
                    ])
                    ->columns(3),
            ]);
    }

    /**
     * Get form components array for modal/embedded forms (without tenant_id)
     */
    public static function getComponents(): array
    {
        return [
            Section::make('Campus Information')
                ->description('Enter the campus name and contact details')
                ->schema([
                    TextInput::make('name')
                        ->label('Campus Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Kuala Lumpur Campus')
                        ->suffixIcon('heroicon-m-building-office-2')
                        ->columnSpanFull()
                        ->autofocus(),

                    TextInput::make('phone')
                        ->label('Phone Number')
                        ->tel()
                        ->maxLength(255)
                        ->placeholder('+60 3-1234 5678')
                        ->suffixIcon('heroicon-m-phone'),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->email()
                        ->maxLength(255)
                        ->placeholder('campus@example.com')
                        ->suffixIcon('heroicon-m-envelope'),
                ])
                ->columns(2),

            Section::make('Location')
                ->description('Provide the complete campus address')
                ->schema([
                    TextInput::make('address_1')
                        ->label('Address Line 1')
                        ->maxLength(255)
                        ->placeholder('123 Jalan Example')
                        ->columnSpanFull(),

                    TextInput::make('address_2')
                        ->label('Address Line 2')
                        ->maxLength(255)
                        ->placeholder('Taman Test (optional)')
                        ->helperText('Additional address details if needed')
                        ->columnSpanFull(),

                    TextInput::make('postal_code')
                        ->label('Postal Code')
                        ->maxLength(5)
                        ->placeholder('50000')
                        ->mask('99999')
                        ->suffixIcon('heroicon-m-hashtag'),

                    TextInput::make('city')
                        ->label('City')
                        ->maxLength(255)
                        ->placeholder('Kuala Lumpur')
                        ->suffixIcon('heroicon-m-map-pin'),

                    TextInput::make('state')
                        ->label('State')
                        ->maxLength(255)
                        ->placeholder('Selangor')
                        ->suffixIcon('heroicon-m-map'),
                ])
                ->columns(3),
        ];
    }
}
