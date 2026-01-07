<?php

namespace App\Livewire;

use App\Actions\Registration\CompleteParentRegistrationAction;
use App\Actions\Registration\CreateChildrenForParentAction;
use App\Actions\Registration\RegisterParentBasicInfoAction;
use App\Actions\Registration\UpdateParentDetailsAction;
use App\Http\Requests\AgreementRequest;
use App\Http\Requests\ChildInformationRequest;
use App\Http\Requests\ParentBasicInfoRequest;
use App\Http\Requests\ParentDetailsRequest;
use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class TenantRegistrationWizard extends Component implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    public Tenant $tenant;

    public int $currentStep = 1;

    public ?string $emailFromQuery = null;

    // Step 1 - Parent Basic Info
    public ?string $name = null;

    public ?string $mykad_number = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public array $centre_ids = [];

    // Step 2 - Parent Details
    public ?string $occupation = null;

    public ?string $address = null;

    public ?string $postal_code = null;

    public ?string $city = null;

    public ?string $state = null;

    public ?string $office_name = null;

    public ?string $office_address = null;

    public ?string $office_postal_code = null;

    public ?string $office_city = null;

    public ?string $office_state = null;

    public $profile_photo = null;

    public $mykad_image = null;

    public $immunization_card = null;

    public bool $information_confirmed = false;

    // Step 3 - Children
    public array $children = [];

    // Step 4 - Agreement
    public bool $tnc_accepted = false;

    public bool $undertaking_accepted = false;

    protected array $selectedCentres = [];

    public function mount(Tenant $tenant, ?string $email = null, ?int $step = null): void
    {
        $this->tenant = $tenant;
        $this->emailFromQuery = $email;

        // Pre-fill email if provided in query params
        if ($this->emailFromQuery) {
            $this->email = $this->emailFromQuery;
        }

        // Load saved registration data if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $this->loadSavedRegistrationData($user);
            $this->currentStep = $step ?? $user->getCurrentRegistrationStep() ?? 1;
        } elseif ($step) {
            $this->currentStep = $step;
        }
    }

    protected function loadSavedRegistrationData(User $user): void
    {
        // Step 1 data - load from registration_data if exists, otherwise from database
        $step1Data = $user->getRegistrationData('step_1');
        if ($step1Data) {
            $this->name = $step1Data['name'] ?? $user->name;
            $this->email = $step1Data['email'] ?? $user->email;
            $this->phone = $user->profile?->phone ?? null;
            $this->mykad_number = $user->profile?->nric ?? null;
            $this->centre_ids = $step1Data['centre_ids'] ?? [];
        } else {
            // Load from database for existing users
            $this->name = $user->name;
            $this->email = $user->email;
            $this->phone = $user->profile?->phone ?? null;
            $this->mykad_number = $user->profile?->nric ?? null;
            $this->centre_ids = $user->centres->pluck('id')->toArray();
        }

        // Step 2 data - load from registration_data if exists, otherwise from database
        $step2Data = $user->getRegistrationData('step_2');
        if ($step2Data) {
            $this->occupation = $user->profile?->occupation ?? null;
            $this->address = $user->userAddress?->address ?? null;
            $this->postal_code = $user->userAddress?->postal_code ?? null;
            $this->city = $user->userAddress?->city ?? null;
            $this->state = $user->userAddress?->state_code?->value ?? null;
            $this->office_name = $user->officeInfo?->name ?? null;
            $this->office_address = $user->officeInfo?->office_address ?? null;
            $this->office_postal_code = $user->officeInfo?->office_postal_code ?? null;
            $this->office_city = $user->officeInfo?->office_city ?? null;
            $this->office_state = $user->officeInfo?->office_state_code?->value ?? null;
            $this->information_confirmed = $step2Data['information_confirmed'] ?? false;
        } else {
            // Load from database for existing users
            $this->occupation = $user->profile?->occupation ?? null;
            $this->address = $user->userAddress?->address ?? null;
            $this->postal_code = $user->userAddress?->postal_code ?? null;
            $this->city = $user->userAddress?->city ?? null;
            $this->state = $user->userAddress?->state_code?->value ?? null;
            $this->office_name = $user->officeInfo?->name ?? null;
            $this->office_address = $user->officeInfo?->office_address ?? null;
            $this->office_postal_code = $user->officeInfo?->office_postal_code ?? null;
            $this->office_city = $user->officeInfo?->office_city ?? null;
            $this->office_state = $user->officeInfo?->office_state_code?->value ?? null;
        }

        // Step 3 data - always prioritize database children if they exist
        if ($user->children->isNotEmpty()) {
            // Load children from database (source of truth)
            $this->children = $user->children->map(fn ($child) => [
                'first_name' => $child->first_name ?? '',
                'patronymic' => $child->patronymic ?? '',
                'last_name' => $child->last_name ?? '',
                'gender' => $child->gender ?? '',
                'date_of_birth' => $child->date_of_birth ? $child->date_of_birth->format('Y-m-d') : '',
                'place_of_birth' => $child->place_of_birth ?? '',
                'race' => $child->race ?? '',
                'religion' => $child->religion ?? '',
                'position_of_child' => $child->position_of_child ?? '',
                'mykid_no' => $child->mykid_no ?? '',
                'cert_number' => $child->cert_number ?? '',
            ])->toArray();
        } else {
            // Fall back to registration_data only if no children in database
            $step3Data = $user->getRegistrationData('step_3');
            $this->children = $step3Data['children'] ?? [];
        }

        // Step 4 data
        $step4Data = $user->getRegistrationData('step_4');
        if ($step4Data) {
            $this->tnc_accepted = $step4Data['tnc_accepted'] ?? false;
            $this->undertaking_accepted = $step4Data['undertaking_accepted'] ?? false;
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema($this->getCurrentStepSchema());
    }

    protected function getCurrentStepSchema(): array
    {
        return match ($this->currentStep) {
            1 => $this->getStep1Schema(),
            2 => $this->getStep2Schema(),
            3 => $this->getStep3Schema(),
            4 => $this->getStep4Schema(),
            default => [],
        };
    }

    protected function getStep1Schema(): array
    {
        return [
            Section::make('Basic Information')
                ->description('Please provide your basic information to get started.')
                ->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Enter your full name'),

                    TextInput::make('mykad_number')
                        ->label('MyKad Number')
                        ->required()
                        ->maxLength(12)
                        ->placeholder('e.g., 900101011234')
                        ->helperText('Enter your 12-digit MyKad number without dashes'),

                    TextInput::make('phone')
                        ->label('Phone Number')
                        ->required()
                        ->tel()
                        ->maxLength(20)
                        ->placeholder('e.g., +60123456789'),

                    TextInput::make('email')
                        ->label('Email Address')
                        ->required()
                        ->email()
                        ->maxLength(255)
                        ->placeholder('your.email@example.com'),

                    TextInput::make('password')
                        ->label('Password')
                        ->required(fn () => ! Auth::check())
                        ->password()
                        ->minLength(8)
                        ->same('password_confirmation')
                        ->helperText('Must be at least 8 characters'),

                    TextInput::make('password_confirmation')
                        ->label('Confirm Password')
                        ->required(fn () => ! Auth::check())
                        ->password()
                        ->dehydrated(false),

                    Select::make('centre_ids')
                        ->label('Select Centre(s)')
                        ->required()
                        ->multiple()
                        ->options(fn () => Centre::where('tenant_id', $this->tenant->id)
                            ->pluck('name', 'id'))
                        ->helperText('Select one or more centres. This cannot be changed later.')
                        ->disabled(fn () => $this->currentStep > 1 && ! empty($this->centre_ids))
                        ->searchable(),
                ]),
        ];
    }

    protected function getStep2Schema(): array
    {
        $centreNames = Centre::whereIn('id', $this->centre_ids)->pluck('name')->implode(', ');

        return [
            // Display locked centres
            ViewField::make('locked_centres')
                ->label('Selected Centres')
                ->view('filament.forms.components.locked-centres')
                ->viewData(['centres' => $centreNames])
                ->helperText('Centre selection is locked after Step 1'),

            Section::make('Personal Details')
                ->schema([
                    TextInput::make('occupation')
                        ->label('Occupation')
                        ->maxLength(255)
                        ->placeholder('Your occupation'),

                    FileUpload::make('profile_photo')
                        ->label('Profile Photo')
                        ->image()
                        ->maxSize(5120)
                        ->helperText('Optional. Upload a profile photo (max 5MB). You can upload this later from your dashboard.'),

                    FileUpload::make('mykad_image')
                        ->label('MyKad Image')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                        ->maxSize(10240)
                        ->helperText('Optional. Upload a copy of your MyKad (max 10MB). Accepted formats: JPG, PNG, PDF. You can upload this later from your dashboard.'),

                    FileUpload::make('immunization_card')
                        ->label('Child Immunization Card')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                        ->maxSize(10240)
                        ->helperText('Optional. Upload your child\'s immunization card (max 10MB). Accepted formats: JPG, PNG, PDF. You can upload this later from your dashboard.'),
                ]),

            Section::make('Address Information')
                ->schema([
                    TextInput::make('address')
                        ->label('Address')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('postal_code')
                        ->label('Postal Code')
                        ->required()
                        ->maxLength(10),

                    TextInput::make('city')
                        ->label('City')
                        ->required()
                        ->maxLength(100),

                    Select::make('state')
                        ->label('State')
                        ->required()
                        ->options([
                            'JHR' => 'Johor',
                            'KDH' => 'Kedah',
                            'KTN' => 'Kelantan',
                            'KUL' => 'Kuala Lumpur',
                            'LBN' => 'Labuan',
                            'MLK' => 'Melaka',
                            'NSN' => 'Negeri Sembilan',
                            'PHG' => 'Pahang',
                            'PNG' => 'Penang',
                            'PRK' => 'Perak',
                            'PLS' => 'Perlis',
                            'PJY' => 'Putrajaya',
                            'SBH' => 'Sabah',
                            'SGR' => 'Selangor',
                            'SWK' => 'Sarawak',
                            'TRG' => 'Terengganu',
                        ])
                        ->searchable(),
                ])
                ->columns(3),

            Section::make('Office Information (Optional)')
                ->description('Provide your office details if applicable')
                ->schema([
                    TextInput::make('office_name')
                        ->label('Office Name')
                        ->maxLength(255),

                    TextInput::make('office_address')
                        ->label('Office Address')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('office_postal_code')
                        ->label('Office Postal Code')
                        ->maxLength(10),

                    TextInput::make('office_city')
                        ->label('Office City')
                        ->maxLength(100),

                    Select::make('office_state')
                        ->label('Office State')
                        ->options([
                            'JHR' => 'Johor',
                            'KDH' => 'Kedah',
                            'KTN' => 'Kelantan',
                            'KUL' => 'Kuala Lumpur',
                            'LBN' => 'Labuan',
                            'MLK' => 'Melaka',
                            'NSN' => 'Negeri Sembilan',
                            'PHG' => 'Pahang',
                            'PNG' => 'Penang',
                            'PRK' => 'Perak',
                            'PLS' => 'Perlis',
                            'PJY' => 'Putrajaya',
                            'SBH' => 'Sabah',
                            'SGR' => 'Selangor',
                            'SWK' => 'Sarawak',
                            'TRG' => 'Terengganu',
                        ])
                        ->searchable(),
                ])
                ->columns(3)
                ->collapsible(),

            Checkbox::make('information_confirmed')
                ->label('I confirm that all information provided is accurate and complete')
                ->required()
                ->accepted(),
        ];
    }

    protected function getStep3Schema(): array
    {
        return [
            Section::make('Child Information')
                ->description('Add your children\'s information. This step is optional and can be completed later.')
                ->schema([
                    Repeater::make('children')
                        ->label('Children')
                        ->schema([
                            TextInput::make('first_name')
                                ->label('First Name')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('last_name')
                                ->label('Last Name')
                                ->required()
                                ->maxLength(255),

                            DatePicker::make('date_of_birth')
                                ->label('Date of Birth')
                                ->required()
                                ->maxDate(now())
                                ->native(false),

                            Select::make('gender')
                                ->label('Gender')
                                ->required()
                                ->options([
                                    'male' => 'Male',
                                    'female' => 'Female',
                                ]),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add Child')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => ($state['first_name'] ?? '').' '.($state['last_name'] ?? '')),
                ]),
        ];
    }

    protected function getStep4Schema(): array
    {
        return [
            Section::make('Terms and Conditions')
                ->description('Please read and accept the following terms to complete your registration.')
                ->schema([
                    Checkbox::make('tnc_accepted')
                        ->label('I have read and accept the Terms and Conditions')
                        ->required()
                        ->accepted()
                        ->helperText('You must accept the terms and conditions to proceed'),

                    Checkbox::make('undertaking_accepted')
                        ->label('I have read and accept the Letter of Undertaking')
                        ->required()
                        ->accepted()
                        ->helperText('You must accept the letter of undertaking to proceed'),
                ]),
        ];
    }

    public function addChild(): void
    {
        $this->children[] = [
            'first_name' => '',
            'patronymic' => '',
            'last_name' => '',
            'gender' => '',
            'date_of_birth' => '',
            'place_of_birth' => '',
            'race' => '',
            'religion' => '',
            'position_of_child' => '',
            'mykid_no' => '',
            'cert_number' => '',
        ];
    }

    public function submitStep1(): void
    {
        // Build validation rules
        $rules = (new ParentBasicInfoRequest)->rules();

        // If user is already authenticated with incomplete profile, modify validation rules
        if (Auth::check() && ! Auth::user()->profile_completed) {
            // Remove unique constraint for email (user can update their own email)
            $rules['email'] = ['required', 'email', 'max:255', Rule::unique('users')->ignore(Auth::id())];

            // Make password optional for existing users
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];

            // Make mykad_number optional for existing users
            $rules['mykad_number'] = ['sometimes', 'string', 'max:12'];
        }

        // Validate step 1 data
        $validator = Validator::make(
            [
                'name' => $this->name,
                'mykad_number' => $this->mykad_number,
                'phone' => $this->phone,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'centre_ids' => $this->centre_ids,
            ],
            $rules
        );

        if ($validator->fails()) {
            // Add errors to Livewire's error bag
            $this->setErrorBag($validator->errors());

            return;
        }

        // Execute action
        try {
            $action = new RegisterParentBasicInfoAction;
            $result = $action->execute(
                $validator->validated(),
                $this->tenant,
                Auth::check() ? Auth::user() : null
            );

            // Auto-login if this is a new user
            if ($result['shouldLogin']) {
                Auth::login($result['user']);
            }

            $this->nextStep();
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            \Log::error('Error in Step 1 submission: '.$e->getMessage());
        }
    }

    public function submitStep2(): void
    {
        // Validate step 2 data
        $validator = Validator::make(
            [
                'occupation' => $this->occupation,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'state' => $this->state,
                'office_name' => $this->office_name,
                'office_address' => $this->office_address,
                'office_postal_code' => $this->office_postal_code,
                'office_city' => $this->office_city,
                'office_state' => $this->office_state,
                'profile_photo' => $this->profile_photo,
                'mykad_image' => $this->mykad_image,
                'immunization_card' => $this->immunization_card,
                'information_confirmed' => $this->information_confirmed,
            ],
            (new ParentDetailsRequest)->rules()
        );

        if ($validator->fails()) {
            // Add errors to Livewire's error bag
            $this->setErrorBag($validator->errors());

            return;
        }

        // Execute action
        try {
            $action = new UpdateParentDetailsAction;
            $action->execute(Auth::user(), $validator->validated());

            $this->nextStep();
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            \Log::error('Error in Step 2 submission: '.$e->getMessage());
        }
    }

    public function submitStep3(): void
    {
        // Validate step 3 data
        $validator = Validator::make(
            ['children' => $this->children],
            (new ChildInformationRequest)->rules()
        );

        if ($validator->fails()) {
            // Add errors to Livewire's error bag
            $this->setErrorBag($validator->errors());

            return;
        }

        // Execute action
        try {
            $action = new CreateChildrenForParentAction;

            $action->execute(Auth::user(), $this->children);

            $message = empty($this->children)
                ? 'Step 3 Skipped. You can add children later from your dashboard.'
                : 'Step 3 Completed. Your children have been saved successfully.';

            $this->nextStep();
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            \Log::error('Error in Step 3 submission: '.$e->getMessage());
        }
    }

    public function submitStep4()
    {
        // Validate step 4 data
        $validator = Validator::make(
            [
                'tnc_accepted' => $this->tnc_accepted,
                'undertaking_accepted' => $this->undertaking_accepted,
            ],
            (new AgreementRequest)->rules()
        );

        if ($validator->fails()) {
            // Add errors to Livewire's error bag
            $this->setErrorBag($validator->errors());

            return;
        }

        // Execute action
        try {
            $action = new CompleteParentRegistrationAction;
            $action->execute(Auth::user(), $validator->validated());

            // Redirect to parent dashboard
            return redirect()->route('filament.app.pages.dashboard');
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
            \Log::error('Error in Step 4 submission: '.$e->getMessage());
        }
    }

    public function nextStep(): void
    {
        if ($this->currentStep < 4) {
            $this->currentStep++;
            $this->dispatch('step-changed', step: $this->currentStep);
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
            $this->dispatch('step-changed', step: $this->currentStep);
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 4) {
            // Only allow going back or to current step
            if (Auth::check() && $step <= Auth::user()->getCurrentRegistrationStep()) {
                $this->currentStep = $step;
                $this->dispatch('step-changed', step: $this->currentStep);
            }
        }
    }

    public function render()
    {
        return view('livewire.tenant-registration-wizard')
            ->layout('layouts.guest');
    }
}
