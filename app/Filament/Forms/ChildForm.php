<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;

class ChildForm
{
    public static function make(bool $includeAssociatedUsers = true, bool $includeRelationshipType = false): array
    {
        $schema = [
            Tabs::make('Child Information')
                ->tabs([
                    Tabs\Tab::make('Basic Information')
                        ->icon('heroicon-o-user')
                        ->schema(static::basic()),

                    Tabs\Tab::make('Identification')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Section::make('Government Identification')
                                ->description('Official identification documents')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('mykid_no')
                                                ->label('MyKid Number')
                                                ->required()
                                                ->maxLength(255)
                                                ->placeholder('e.g., 150101010001')
                                                ->helperText('Malaysian MyKid identification number')
                                                ->suffixIcon('heroicon-m-identification')
                                                ->mask('999999999999'),

                                            TextInput::make('cert_number')
                                                ->label('Birth Certificate Number')
                                                ->maxLength(255)
                                                ->placeholder('e.g., A12345678')
                                                ->helperText('Birth certificate registration number')
                                                ->suffixIcon('heroicon-m-document-text'),
                                        ]),
                                ]),
                        ]),

                    Tabs\Tab::make('Health')
                        ->icon('heroicon-o-heart')
                        ->schema([
                            Section::make('Medical Information')
                                ->description('Important health and medical details')
                                ->schema([
                                    Textarea::make('allergies')
                                        ->label('Allergies')
                                        ->placeholder('List any known allergies (e.g., peanuts, dairy, medications)')
                                        ->helperText('Please be specific to ensure child safety')
                                        ->rows(3)
                                        ->maxLength(65535)
                                        ->columnSpanFull(),

                                    Textarea::make('diseases')
                                        ->label('Medical Conditions')
                                        ->placeholder('List any medical conditions or chronic diseases (e.g., asthma, diabetes)')
                                        ->helperText('Include any ongoing treatments or medications')
                                        ->rows(3)
                                        ->maxLength(65535)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Family Clinic')
                                ->description('Primary healthcare provider details')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('family_clinic')
                                                ->label('Clinic Name')
                                                ->maxLength(255)
                                                ->placeholder('e.g., Klinik Kesihatan Bangsar')
                                                ->suffixIcon('heroicon-m-building-office-2'),

                                            TextInput::make('family_clinic_phone')
                                                ->label('Clinic Phone')
                                                ->tel()
                                                ->maxLength(255)
                                                ->placeholder('+60312345678')
                                                ->helperText('Emergency contact number')
                                                ->suffixIcon('heroicon-m-phone'),
                                        ]),
                                ])
                                ->collapsible(),
                        ]),

                    Tabs\Tab::make('Documents')
                        ->icon('heroicon-o-document')
                        ->schema([
                            Section::make('Child Documents')
                                ->description('Upload important documents and photos')
                                ->schema([
                                    SpatieMediaLibraryFileUpload::make('photo')
                                        ->label('Profile Photo')
                                        ->collection('photo')
                                        ->disk('private')
                                        ->image()
                                        ->imagePreviewHeight('250')
                                        ->loadingIndicatorPosition('center')
                                        ->panelAspectRatio('1:1')
                                        ->panelLayout('circle')
                                        ->removeUploadedFileButtonPosition('center')
                                        ->uploadButtonPosition('center')
                                        ->uploadProgressIndicatorPosition('center')
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                        ->maxSize(5120)
                                        ->helperText('JPG, PNG, or WebP. Maximum 5MB')
                                        ->columnSpanFull(),

                                    Grid::make(2)
                                        ->schema([
                                            SpatieMediaLibraryFileUpload::make('birth_certificate')
                                                ->label('Birth Certificate')
                                                ->collection('birth_certificate')
                                                ->disk('private')
                                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                                ->maxSize(10240)
                                                ->panelLayout('compact')
                                                ->downloadable()
                                                ->openable()
                                                ->helperText('JPG, PNG, WebP, or PDF. Maximum 10MB'),

                                            SpatieMediaLibraryFileUpload::make('immunization_card')
                                                ->label('Immunization Card')
                                                ->collection('immunization_card')
                                                ->disk('private')
                                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                                ->maxSize(10240)
                                                ->panelLayout('compact')
                                                ->downloadable()
                                                ->openable()
                                                ->helperText('JPG, PNG, WebP, or PDF. Maximum 10MB'),
                                        ]),
                                ]),
                        ]),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
        ];

        // Add relationship type section if requested (for relation managers)
        if ($includeRelationshipType) {
            $schema[] = Section::make('Relationship')
                ->icon('heroicon-o-user-group')
                ->schema([
                    Select::make('relationship_type')
                        ->label('Relationship to Child')
                        ->options([
                            'parent' => 'Parent',
                            'guardian' => 'Legal Guardian',
                            'relative' => 'Relative',
                            'other' => 'Other',
                        ])
                        ->default('parent')
                        ->required()
                        ->native(false)
                        ->helperText('Specify your relationship to this child'),
                ])
                ->columnSpanFull();
        }

        // Add associated users section if requested (for main resource)
        if ($includeAssociatedUsers) {
            $schema[] = Section::make('Parents & Guardians')
                ->icon('heroicon-o-user-group')
                ->description('Link parents or guardians to this child')
                ->schema([
                    Select::make('users')
                        ->label('Parents/Guardians')
                        ->multiple()
                        ->relationship('users', 'name')
                        ->preload()
                        ->searchable()
                        ->native(false)
                        ->helperText('Select existing users or create new ones')
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Ahmad bin Abdullah'),

                            TextInput::make('email')
                                ->label('Email Address')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->placeholder('example@email.com'),

                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->maxLength(255)
                                ->revealable()
                                ->helperText('Minimum 8 characters'),

                            Select::make('relationship_type')
                                ->label('Relationship to Child')
                                ->options([
                                    'parent' => 'Parent',
                                    'guardian' => 'Legal Guardian',
                                    'relative' => 'Relative',
                                    'other' => 'Other',
                                ])
                                ->default('parent')
                                ->required()
                                ->native(false),
                        ])
                        ->optionsLimit(100)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->columnSpanFull();
        }

        return $schema;
    }

    /**
     * Get the standalone basic information section used by the full child form.
     */
    public static function basic(): array
    {
        return [
            Section::make('Basic Information')
                ->icon('heroicon-o-user')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('first_name')
                                ->label('First Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Muhammad')
                                ->suffixIcon('heroicon-m-user'),

                            TextInput::make('patronymic')
                                ->label('Middle Name')
                                ->maxLength(255)
                                ->placeholder('Optional')
                                ->suffixIcon('heroicon-m-user'),

                            TextInput::make('last_name')
                                ->label('Last Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., Abdullah')
                                ->suffixIcon('heroicon-m-user'),
                        ]),

                    Section::make('Personal Details')
                        ->schema([
                            Grid::make(3)
                                ->schema([
                                    Select::make('gender')
                                        ->required()
                                        ->options([
                                            'male' => 'Male',
                                            'female' => 'Female',
                                        ])
                                        ->native(false)
                                        ->prefixIcon('heroicon-m-users'),

                                    DatePicker::make('date_of_birth')
                                        ->label('Date of Birth')
                                        ->required()
                                        ->maxDate(now())
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->prefixIcon('heroicon-m-cake')
                                        ->helperText('Child\'s birth date'),

                                    TextInput::make('place_of_birth')
                                        ->label('Place of Birth')
                                        ->maxLength(255)
                                        ->placeholder('e.g., Kuala Lumpur')
                                        ->suffixIcon('heroicon-m-map-pin'),
                                ]),

                            Grid::make(3)
                                ->schema([
                                    Select::make('race')
                                        ->label('Race')
                                        ->options([
                                            'Malay' => 'Malay',
                                            'Chinese' => 'Chinese',
                                            'Indian' => 'Indian',
                                            'Bumiputera Sabah' => 'Bumiputera Sabah',
                                            'Bumiputera Sarawak' => 'Bumiputera Sarawak',
                                            'Orang Asli' => 'Orang Asli',
                                            'Other' => 'Other',
                                        ])
                                        ->searchable()
                                        ->native(false)
                                        ->prefixIcon('heroicon-m-globe-alt')
                                        ->placeholder('Select race'),

                                    Select::make('religion')
                                        ->label('Religion')
                                        ->options([
                                            'Islam' => 'Islam',
                                            'Christianity' => 'Christianity',
                                            'Buddhism' => 'Buddhism',
                                            'Hinduism' => 'Hinduism',
                                            'Sikhism' => 'Sikhism',
                                            'Taoism' => 'Taoism',
                                            'Other' => 'Other',
                                            'No Religion' => 'No Religion',
                                        ])
                                        ->searchable()
                                        ->native(false)
                                        ->prefixIcon('heroicon-m-heart')
                                        ->placeholder('Select religion'),

                                    TextInput::make('position_of_child')
                                        ->label('Position in Family')
                                        ->helperText('e.g., 1st child, 2nd child')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(20)
                                        ->suffixIcon('heroicon-m-numbered-list')
                                        ->placeholder('1'),
                                ]),

                            Select::make('languages')
                                ->label('Languages Spoken')
                                ->multiple()
                                ->options([
                                    'Malay' => 'Malay',
                                    'English' => 'English',
                                    'Mandarin' => 'Mandarin',
                                    'Tamil' => 'Tamil',
                                    'Cantonese' => 'Cantonese',
                                    'Hokkien' => 'Hokkien',
                                    'Arabic' => 'Arabic',
                                    'Other' => 'Other',
                                ])
                                ->searchable()
                                ->native(false)
                                ->prefixIcon('heroicon-m-language')
                                ->helperText('Select all languages the child can speak')
                                ->placeholder('Select languages')
                                ->columnSpanFull(),
                        ])
                        ->collapsible(),
                ]),
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
