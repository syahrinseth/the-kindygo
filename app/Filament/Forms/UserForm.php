<?php

namespace App\Filament\Forms;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function make(): array
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

            Forms\Components\Section::make('User Detail Information')
                ->description('Personal identification and address information for this user')
                ->schema([
                    Forms\Components\TextInput::make('nric')
                        ->label('NRIC/IC Number')
                        ->placeholder('e.g., 950101014321')
                        ->helperText('Malaysian NRIC/IC number for individual customers')
                        ->maxLength(12)
                        ->rules(['nullable', 'digits:12'])
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            // Clear passport if NRIC is entered
                            if (filled($state)) {
                                $set('passport', null);
                            }
                        }),
                    
                    Forms\Components\TextInput::make('passport')
                        ->label('Passport Number')
                        ->placeholder('e.g., A12345678')
                        ->helperText('Passport number for foreign customers (if no NRIC)')
                        ->maxLength(20)
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            // Clear NRIC if passport is entered
                            if (filled($state)) {
                                $set('nric', null);
                            }
                        }),
                        
                    Forms\Components\Textarea::make('address')
                        ->label('Address')
                        ->placeholder('e.g., 123 Jalan Test, Taman Example')
                        ->helperText('Full street address for e-Invoice')
                        ->rows(2)
                        ->maxLength(500),
                    
                    Forms\Components\TextInput::make('city')
                        ->label('City')
                        ->placeholder('e.g., Kuala Lumpur')
                        ->maxLength(100),
                        
                    Forms\Components\TextInput::make('postal_code')
                        ->label('Postal Code')
                        ->placeholder('e.g., 50000')
                        ->maxLength(10)
                        ->rules(['nullable', 'regex:/^\d{5}$/']),
                        
                    Forms\Components\Select::make('state_code')
                        ->label('State')
                        ->placeholder('Select state')
                        ->options([
                            '01' => 'Johor',
                            '02' => 'Kedah',
                            '03' => 'Kelantan',
                            '04' => 'Melaka',
                            '05' => 'Negeri Sembilan',
                            '06' => 'Pahang',
                            '07' => 'Pulau Pinang',
                            '08' => 'Perak',
                            '09' => 'Perlis',
                            '10' => 'Selangor',
                            '11' => 'Terengganu',
                            '12' => 'Sabah',
                            '13' => 'Sarawak',
                            '14' => 'Wilayah Persekutuan Kuala Lumpur',
                            '15' => 'Wilayah Persekutuan Labuan',
                            '16' => 'Wilayah Persekutuan Putrajaya',
                        ])
                        ->searchable(),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(false),

            Forms\Components\Section::make('Documents & Photos')
                ->schema([

                    SpatieMediaLibraryFileUpload::make('photo')
                        ->label('Photo')
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
                        ->helperText('Upload photo. Maximum size: 5MB'),
                        
                    SpatieMediaLibraryFileUpload::make('mykad')
                        ->label('MyKad')
                        ->collection('mykad')
                        ->disk('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->panelLayout('compact')
                        ->downloadable()
                        ->openable()
                        ->helperText('Upload MyKad document. Accepted formats: JPG, PNG, WebP, PDF. Maximum size: 10MB'),

                    SpatieMediaLibraryFileUpload::make('immunization_card')
                        ->label('Immunization Card')
                        ->collection('immunization_card')
                        ->disk('private')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(10240) // 10MB
                        ->panelLayout('compact')
                        ->downloadable()
                        ->openable()
                        ->helperText('Upload immunization card. Accepted formats: JPG, PNG, WebP, PDF. Maximum size: 10MB'),
                ])->columns(3),

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
                                $parentRole = Role::where('name', 'Parent')->first();
                                if ($parentRole) {
                                    $component->state([$parentRole->id]);
                                }
                            }
                        })
                ])
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
