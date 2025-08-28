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

            Forms\Components\Section::make('Business Information')
                ->description('Required for e-Invoice generation and LHDN compliance')
                ->schema([
                    Forms\Components\TextInput::make('tax_identification_number')
                        ->label('Tax Identification Number (TIN)')
                        ->helperText('Required for e-Invoice submission to LHDN')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('business_activity_code')
                        ->label('Business Activity Code')
                        ->helperText('MSIC code (e.g., 85100 for childcare)')
                        ->maxLength(10)
                        ->default('85100'),
                    Forms\Components\TextInput::make('business_activity_description')
                        ->label('Business Activity Description')
                        ->maxLength(255)
                        ->default('Child day-care activities'),
                    Forms\Components\Group::make([
                        Forms\Components\Select::make('business_id_type')
                            ->label('Business ID Type')
                            ->options([
                                'NRIC' => 'NRIC (National Registration Identity Card)',
                                'BRN' => 'BRN (Business Registration Number)',
                                'PASSPORT' => 'Passport',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Select the type of business identification'),
                        Forms\Components\TextInput::make('business_id_value')
                            ->label('Business ID Value')
                            ->required()
                            ->maxLength(50)
                            ->helperText(function (Forms\Get $get) {
                                return match ($get('business_id_type')) {
                                    'NRIC' => 'Enter 12 digits without dashes (e.g., 920728015777)',
                                    'BRN' => 'Enter your Business Registration Number',
                                    'PASSPORT' => 'Enter your passport number',
                                    default => 'Enter the ID value corresponding to the selected ID type',
                                };
                            })
                            ->live()
                            ->rules(function (Forms\Get $get) {
                                $rules = ['required', 'max:50'];
                                
                                if ($get('business_id_type') === 'NRIC') {
                                    $rules[] = 'regex:/^[0-9]{12}$/';
                                }
                                
                                return $rules;
                            })
                            ->validationMessages([
                                'regex' => 'NRIC must be exactly 12 digits without dashes (e.g., 920728015777)',
                            ]),
                    ])->columns(2),
                    Forms\Components\Select::make('country')
                        ->label('Country')
                        ->options([
                            'MY' => 'Malaysia',
                            'SG' => 'Singapore',
                            'ID' => 'Indonesia',
                            'TH' => 'Thailand',
                            'VN' => 'Vietnam',
                            'PH' => 'Philippines',
                        ])
                        ->default('MY')
                        ->required(),
                    Forms\Components\TextInput::make('state_code')
                        ->label('State Code')
                        ->helperText('For Malaysian states (e.g., 14 for Selangor)')
                        ->maxLength(10)
                        ->default('14'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('E-Invoice API Configuration')
                ->description('MyInvois API credentials for this tenant. Leave empty to use global configuration.')
                ->schema([
                    Forms\Components\TextInput::make('einvoice_client_id')
                        ->label('MyInvois Client ID')
                        ->helperText('Obtained from MyInvois portal for this tenant')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('einvoice_client_secret')
                        ->label('MyInvois Client Secret')
                        ->helperText('Obtained from MyInvois portal for this tenant')
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                    Forms\Components\Select::make('einvoice_environment')
                        ->label('Environment')
                        ->options([
                            'sandbox' => 'Sandbox (Testing)',
                            'production' => 'Production (Live)',
                        ])
                        ->default('sandbox')
                        ->required()
                        ->helperText('Use sandbox for testing, production for live transactions'),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
        ];
    }
}
