<?php

use App\Enums\MalaysianState;
use App\Livewire\TenantRegistrationWizard;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Kindergarten',
        'slug' => 'test-kindergarten',
    ]);
});

describe('default state initialisation', function () {
    it('sets state to Johor by default for user with no registration_data and no address', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('state', MalaysianState::JOHOR->value);
    });

    it('leaves office_state empty for user with no registration_data', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('office_state', null);
    });

    it('starts at step 1 for user with registration_step 0', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('currentStep', 1);
    });
});

describe('loading from existing profile/address models', function () {
    it('loads name and email from the user model when no registration_data', function () {
        $user = User::factory()->create([
            'name' => 'Ahmad Razif',
            'email' => 'ahmad@example.com',
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('name', 'Ahmad Razif')
            ->assertSet('email', 'ahmad@example.com');
    });

    it('loads phone and nric from existing user profile when no registration_data', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        UserProfile::create([
            'user_id' => $user->id,
            'phone' => '+60123456789',
            'nric' => '900101011234',
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('phone', '+60123456789')
            ->assertSet('mykad_number', '900101011234');
    });

    it('loads state from existing user address model and skips Johor default', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        UserAddress::create([
            'user_id' => $user->id,
            'address' => 'No. 1, Jalan Ampang',
            'city' => 'Petaling Jaya',
            'postal_code' => '47810',
            'state_code' => MalaysianState::SELANGOR,
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('state', MalaysianState::SELANGOR->value);
    });

    it('does not set Johor as default when user already has a state in their address', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 0,
            'registration_data' => null,
        ]);

        UserAddress::create([
            'user_id' => $user->id,
            'address' => 'No. 5, Jalan Ipoh',
            'city' => 'Ipoh',
            'postal_code' => '31400',
            'state_code' => MalaysianState::PERAK,
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('state', MalaysianState::PERAK->value);
    });
});

describe('loading from saved registration_data', function () {
    it('loads state from user address model when registration_data step_2 exists', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 2,
            'registration_data' => [
                'step_1' => [
                    'name' => 'Siti Aminah',
                    'email' => 'siti@example.com',
                    'phone' => '+60111234567',
                    'mykad_number' => '920202021234',
                    'centre_ids' => [],
                    'tenant_id' => $this->tenant->id,
                ],
                'step_2' => [
                    'occupation' => 'Teacher',
                    'information_confirmed' => true,
                ],
            ],
        ]);

        UserAddress::create([
            'user_id' => $user->id,
            'address' => 'No. 10, Jalan Duta',
            'city' => 'Kuala Lumpur',
            'postal_code' => '50480',
            'state_code' => MalaysianState::WP_KUALA_LUMPUR,
        ]);

        // State comes from the address model; Johor default must not override it
        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('state', MalaysianState::WP_KUALA_LUMPUR->value);
    });

    it('sets state to Johor default when registration_data exists but user has no address', function () {
        $user = User::factory()->create([
            'profile_completed' => false,
            'registration_step' => 1,
            'registration_data' => [
                'step_1' => [
                    'name' => 'Farid Hamdan',
                    'email' => 'farid@example.com',
                    'phone' => '+60198765432',
                    'mykad_number' => '850505051234',
                    'centre_ids' => [],
                    'tenant_id' => $this->tenant->id,
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(TenantRegistrationWizard::class, ['tenant' => $this->tenant])
            ->assertSet('state', MalaysianState::JOHOR->value);
    });
});
