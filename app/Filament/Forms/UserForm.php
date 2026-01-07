<?php

namespace App\Filament\Forms;

use App\Enums\MalaysianState;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function make(): array
    {
        return [
            Tabs::make('Parent Information')
                ->tabs([
                    Tabs\Tab::make('Basic Information')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Full Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan(1),

                                    TextInput::make('email')
                                        ->email()
                                        ->required()
                                        ->maxLength(255)
                                        ->unique(User::class, 'email', ignoreRecord: true)
                                        ->columnSpan(1),
                                ]),

                            Section::make('Personal Details')
                                ->description('Identification and contact information')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('profile.nric')
                                                ->label('NRIC/IC Number')
                                                ->placeholder('e.g., 950101014321')
                                                ->helperText('Malaysian NRIC/IC number')
                                                ->maxLength(12)
                                                ->rules(['required_without:profile.passport', 'digits:12'])
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if (filled($state)) {
                                                        $set('profile.passport', null);
                                                    }
                                                })
                                                ->suffixIcon('heroicon-m-identification'),

                                            TextInput::make('profile.passport')
                                                ->label('Passport Number')
                                                ->placeholder('e.g., A12345678')
                                                ->helperText('For non-citizens without NRIC')
                                                ->maxLength(20)
                                                ->requiredIf('profile.nric', null)
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    if (filled($state)) {
                                                        $set('profile.nric', null);
                                                    }
                                                })
                                                ->suffixIcon('heroicon-m-identification'),

                                            TextInput::make('profile.phone')
                                                ->label('Phone Number')
                                                ->placeholder('+60123456789')
                                                ->helperText('Primary contact number')
                                                ->tel()
                                                ->required()
                                                ->maxLength(20)
                                                ->suffixIcon('heroicon-m-phone'),

                                            TextInput::make('profile.occupation')
                                                ->label('Occupation')
                                                ->placeholder('e.g., Teacher, Engineer, Doctor')
                                                ->required()
                                                ->maxLength(100)
                                                ->suffixIcon('heroicon-m-briefcase'),

                                            TextInput::make('profile.tin')
                                                ->label('Tax Identification Number (TIN)')
                                                ->placeholder('C12345678901')
                                                ->helperText('10-20 alphanumeric characters (optional)')
                                                ->maxLength(20)
                                                ->rules(['nullable', 'regex:/^[A-Z0-9]{10,20}$/'])
                                                ->suffixIcon('heroicon-m-document-text')
                                                ->columnSpan(2),
                                        ]),
                                ])
                                ->collapsible(),
                        ]),

                    Tabs\Tab::make('Address')
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Section::make('Home Address')
                                ->description('Residential address for correspondence and e-Invoice')
                                ->schema([
                                    Textarea::make('userAddress.address')
                                        ->label('Address Line 1')
                                        ->placeholder('123 Jalan Test, Taman Example')
                                        ->required()
                                        ->rows(2)
                                        ->maxLength(500)
                                        ->columnSpanFull(),

                                    Textarea::make('userAddress.address_2')
                                        ->label('Address Line 2')
                                        ->placeholder('Unit 5-3, Block A (optional)')
                                        ->rows(1)
                                        ->maxLength(500)
                                        ->columnSpanFull(),

                                    Grid::make(3)
                                        ->schema([
                                            TextInput::make('userAddress.city')
                                                ->label('City')
                                                ->placeholder('Kuala Lumpur')
                                                ->required()
                                                ->maxLength(100),

                                            TextInput::make('userAddress.postal_code')
                                                ->label('Postal Code')
                                                ->placeholder('50000')
                                                ->required()
                                                ->maxLength(10)
                                                ->rules(['required', 'regex:/^\d{5}$/'])
                                                ->mask('99999'),

                                            Select::make('userAddress.state_code')
                                                ->label('State')
                                                ->placeholder('Select state')
                                                ->required()
                                                ->options(MalaysianState::options())
                                                ->searchable()
                                                ->native(false),
                                        ]),
                                ])
                                ->columns(1),

                            Section::make('Office Information')
                                ->description('Office contact details (optional)')
                                ->schema([
                                    TextInput::make('officeInfo.office_phone')
                                        ->label('Office Phone Number')
                                        ->placeholder('+60323456789')
                                        ->tel()
                                        ->maxLength(20)
                                        ->suffixIcon('heroicon-m-phone')
                                        ->columnSpanFull(),

                                    Textarea::make('officeInfo.office_address')
                                        ->label('Office Address Line 1')
                                        ->placeholder('456 Jalan Business, Commercial Center')
                                        ->rows(2)
                                        ->maxLength(500)
                                        ->columnSpanFull(),

                                    Textarea::make('officeInfo.office_address_2')
                                        ->label('Office Address Line 2')
                                        ->placeholder('Suite 10-5, Tower B (optional)')
                                        ->rows(1)
                                        ->maxLength(500)
                                        ->columnSpanFull(),

                                    Grid::make(3)
                                        ->schema([
                                            TextInput::make('officeInfo.office_city')
                                                ->label('Office City')
                                                ->placeholder('Kuala Lumpur')
                                                ->maxLength(100),

                                            TextInput::make('officeInfo.office_postal_code')
                                                ->label('Office Postal Code')
                                                ->placeholder('50000')
                                                ->maxLength(10)
                                                ->rules(['nullable', 'regex:/^\d{5}$/'])
                                                ->mask('99999'),

                                            Select::make('officeInfo.office_state_code')
                                                ->label('Office State')
                                                ->placeholder('Select state')
                                                ->options(MalaysianState::options())
                                                ->searchable()
                                                ->native(false),
                                        ]),
                                ])
                                ->columns(1)
                                ->collapsible()
                                ->collapsed(),
                        ]),

                    Tabs\Tab::make('Documents')
                        ->icon('heroicon-o-document')
                        ->schema([
                            Section::make('Photo & Documents')
                                ->description('Upload identification and medical documents')
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
                                            SpatieMediaLibraryFileUpload::make('mykad')
                                                ->label('MyKad / IC')
                                                ->collection('mykad')
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

                    Tabs\Tab::make('System')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Section::make('Role Assignment')
                                ->description('Assign roles and permissions')
                                ->schema([
                                    Select::make('roles')
                                        ->label('User Roles')
                                        ->multiple()
                                        ->relationship('roles', 'name')
                                        ->options(function () {
                                            $user = Auth::user();
                                            $allRoles = Role::all();

                                            if ($user->hasRole('Super Admin')) {
                                                return $allRoles->pluck('name', 'id');
                                            }

                                            if ($user->hasRole('Admin')) {
                                                return $allRoles->where('name', '!=', 'Super Admin')->pluck('name', 'id');
                                            }

                                            if ($user->hasRole('Principal')) {
                                                return $allRoles->whereIn('name', ['Teacher', 'Parent'])->pluck('name', 'id');
                                            }

                                            return collect();
                                        })
                                        ->preload()
                                        ->required()
                                        ->disabled(function (?User $record = null) {
                                            if (! $record) {
                                                return false;
                                            }

                                            return ! Auth::user()->can('manageRoles', $record);
                                        })
                                        ->afterStateHydrated(function ($component, ?User $record = null) {
                                            if (! $record && empty($component->getState())) {
                                                $parentRole = Role::where('name', 'Parent')->first();
                                                if ($parentRole) {
                                                    $component->state([$parentRole->id]);
                                                }
                                            }
                                        })
                                        ->helperText('Select one or more roles for this user')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Centre Assignments')
                                ->description('Assign centres this user can access')
                                ->schema([
                                    Select::make('centres')
                                        ->label('Assigned Centres')
                                        ->multiple()
                                        ->relationship('centres', 'name', function ($query) {
                                            $user = Auth::user();

                                            if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
                                                return $query;
                                            }

                                            if ($user->hasRole('Principal')) {
                                                return $query->whereHas('users', function ($q) use ($user) {
                                                    $q->where('users.id', $user->id);
                                                });
                                            }

                                            return $query->whereRaw('1 = 0');
                                        })
                                        ->preload()
                                        ->searchable()
                                        ->disabled(function (?User $record = null) {
                                            if (! $record) {
                                                return false;
                                            }

                                            return ! Auth::user()->can('manageCentres', $record);
                                        })
                                        ->helperText('Select centres this user should have access to')
                                        ->columnSpanFull(),
                                ])
                                ->visible(function (?User $record = null) {
                                    if (! $record) {
                                        return Auth::user()->can('manageCentres', new User);
                                    }

                                    return Auth::user()->can('manageCentres', $record);
                                })
                                ->afterStateUpdated(function ($component, $state) {
                                    if (empty($state)) {
                                        $user = $component->getRecord();
                                        if ($user && $user->exists) {
                                            $user->setCurrentCentre(null);
                                        }
                                    }
                                }),

                            Section::make('Password')
                                ->description('Set account password')
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('password')
                                                ->password()
                                                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                                                ->dehydrated(fn ($state) => filled($state))
                                                ->required(fn (string $context): bool => $context === 'create')
                                                ->confirmed()
                                                ->minLength(8)
                                                ->maxLength(255)
                                                ->label(fn (string $context): string => $context === 'edit' ? 'New Password' : 'Password'
                                                )
                                                ->revealable()
                                                ->helperText('Minimum 8 characters'),

                                            TextInput::make('password_confirmation')
                                                ->password()
                                                ->required(fn (string $context): bool => $context === 'create')
                                                ->minLength(8)
                                                ->maxLength(255)
                                                ->label(fn (string $context): string => $context === 'edit' ? 'Confirm New Password' : 'Confirm Password'
                                                )
                                                ->dehydrated(false)
                                                ->revealable()
                                                ->helperText('Re-enter password to confirm'),
                                        ]),
                                ]),
                        ]),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
        ];
    }
}
