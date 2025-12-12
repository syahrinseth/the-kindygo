<?php

namespace App\Filament\Forms;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ChildForm
{
    public static function make(bool $includeAssociatedUsers = true, bool $includeRelationshipType = false): array
    {
        $schema = [
            Section::make('Basic Information')
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('patronymic')
                        ->maxLength(255),
                    Select::make('gender')
                        ->required()
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                        ]),
                    DatePicker::make('date_of_birth')
                        ->required()
                        ->maxDate(now()),
                    TextInput::make('place_of_birth')
                        ->maxLength(255),
                ])->columns(2),
            
            Section::make('Identification')
                ->schema([
                    TextInput::make('mykid_no')
                        ->label('MyKid Number')
                        ->maxLength(255),
                    TextInput::make('cert_number')
                        ->label('Certificate Number')
                        ->maxLength(255),
                    TextInput::make('position_of_child')
                        ->label('Position in Family')
                        ->helperText('E.g. 1st child, 2nd child, etc.')
                        ->numeric()
                        ->minValue(1),
                ])->columns(3),
            
            Section::make('Background')
                ->schema([
                    TextInput::make('race')
                        ->maxLength(255),
                    TextInput::make('religion')
                        ->maxLength(255),
                    TextInput::make('languages')
                        ->helperText('Languages spoken by the child, comma separated')
                        ->maxLength(255),
                ])->columns(3),
            
            Section::make('Health Information')
                ->schema([
                    Textarea::make('allergies')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    Textarea::make('diseases')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    TextInput::make('family_clinic')
                        ->maxLength(255),
                    TextInput::make('family_clinic_phone')
                        ->tel()
                        ->maxLength(255),
                ])->columns(2),
            
            Section::make('Documents & Photos')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('photo')
                        ->label('Child Photo')
                        ->collection('photo')
                        ->disk('private')
                        ->image()
                        ->imagePreviewHeight('250')
                        ->loadingIndicatorPosition('left')
                        ->panelAspectRatio('2:3')
                        ->panelLayout('integrated')
                        ->removeUploadedFileButtonPosition('right')
                        ->uploadButtonPosition('left')
                        ->uploadProgressIndicatorPosition('left')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120) // 5MB
                        ->helperText('Upload a photo of the child. Maximum size: 5MB'),
                        
                    SpatieMediaLibraryFileUpload::make('birth_certificate')
                        ->label('Birth Certificate')
                        ->collection('birth_certificate')
                        ->disk('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->panelLayout('compact')
                        ->downloadable()
                        ->openable()
                        ->helperText('Upload the child\'s birth certificate. Accepted formats: JPG, PNG, WebP, PDF. Maximum size: 10MB'),
                        
                    SpatieMediaLibraryFileUpload::make('immunization_card')
                        ->label('Immunization Card')
                        ->collection('immunization_card')
                        ->disk('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->panelLayout('compact')
                        ->downloadable()
                        ->openable()
                        ->helperText('Upload the child\'s immunization card. Accepted formats: JPG, PNG, WebP, PDF. Maximum size: 10MB'),
                ])->columns(3),
        ];

        // Add relationship type section if requested (for relation managers)
        if ($includeRelationshipType) {
            $schema[] = Section::make('Relationship')
                ->schema([
                    Select::make('relationship_type')
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
            $schema[] = Section::make('Associated Users')
                ->schema([
                    Select::make('users')
                        ->label('Parents/Guardians')
                        ->multiple()
                        ->relationship('users', 'name')
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->maxLength(255),
                            Select::make('relationship_type')
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
            Section::make('Basic Information')
                ->schema([
                    TextInput::make('first_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->required()
                        ->maxLength(255),
                    Select::make('gender')
                        ->required()
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                        ]),
                    DatePicker::make('date_of_birth')
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
