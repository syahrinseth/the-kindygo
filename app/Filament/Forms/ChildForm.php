<?php

namespace App\Filament\Forms;

use Filament\Forms;

class ChildForm
{
    public static function make(bool $includeAssociatedUsers = true, bool $includeRelationshipType = false): array
    {
        $schema = [
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('patronymic')
                        ->maxLength(255),
                    Forms\Components\Select::make('gender')
                        ->required()
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                        ]),
                    Forms\Components\DatePicker::make('date_of_birth')
                        ->required()
                        ->maxDate(now()),
                    Forms\Components\TextInput::make('place_of_birth')
                        ->maxLength(255),
                ])->columns(2),
            
            Forms\Components\Section::make('Identification')
                ->schema([
                    Forms\Components\TextInput::make('mykid_no')
                        ->label('MyKid Number')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('cert_number')
                        ->label('Certificate Number')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('position_of_child')
                        ->label('Position in Family')
                        ->helperText('E.g. 1st child, 2nd child, etc.')
                        ->numeric()
                        ->minValue(1),
                ])->columns(3),
            
            Forms\Components\Section::make('Background')
                ->schema([
                    Forms\Components\TextInput::make('race')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('religion')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('languages')
                        ->helperText('Languages spoken by the child, comma separated')
                        ->maxLength(255),
                ])->columns(3),
            
            Forms\Components\Section::make('Health Information')
                ->schema([
                    Forms\Components\Textarea::make('allergies')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('diseases')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('family_clinic')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('family_clinic_phone')
                        ->tel()
                        ->maxLength(255),
                ])->columns(2),
        ];

        // Add relationship type section if requested (for relation managers)
        if ($includeRelationshipType) {
            $schema[] = Forms\Components\Section::make('Relationship')
                ->schema([
                    Forms\Components\Select::make('relationship_type')
                        ->label('Relationship')
                        ->options([
                            'parent' => 'Parent',
                            'guardian' => 'Guardian',
                            'relative' => 'Relative',
                            'other' => 'Other',
                        ])
                        ->default('parent')
                        ->required(),
                ]);
        }

        // Add associated users section if requested (for main resource)
        if ($includeAssociatedUsers) {
            $schema[] = Forms\Components\Section::make('Associated Users')
                ->schema([
                    Forms\Components\Select::make('users')
                        ->label('Parents/Guardians')
                        ->multiple()
                        ->relationship('users', 'name')
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->maxLength(255),
                            Forms\Components\Select::make('relationship_type')
                                ->label('Relationship')
                                ->options([
                                    'parent' => 'Parent',
                                    'guardian' => 'Guardian',
                                    'relative' => 'Relative',
                                    'other' => 'Other',
                                ])
                                ->default('parent')
                                ->required(),
                        ])
                        ->optionsLimit(50)
                ])->columnSpanFull();
        }

        return $schema;
    }

    /**
     * Get basic form schema with only essential fields
     */
    public static function basic(): array
    {
        return [
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('gender')
                        ->required()
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                        ]),
                    Forms\Components\DatePicker::make('date_of_birth')
                        ->required()
                        ->maxDate(now()),
                ])->columns(2),
        ];
    }

    /**
     * Get form schema with relationship type for relation managers
     */
    public static function withRelationship(): array
    {
        return static::make(includeAssociatedUsers: false, includeRelationshipType: true);
    }

    /**
     * Get full form schema without associated users (for relation managers)
     */
    public static function withoutAssociatedUsers(): array
    {
        return static::make(includeAssociatedUsers: false);
    }
}
