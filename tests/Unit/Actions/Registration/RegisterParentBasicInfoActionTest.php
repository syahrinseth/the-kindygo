<?php

use App\Actions\Registration\RegisterParentBasicInfoAction;
use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->action = new RegisterParentBasicInfoAction;

    // Create the Parent role
    Role::create(['name' => 'Parent', 'guard_name' => 'web']);

    // Create a tenant with centres
    $this->tenant = Tenant::factory()->create();
    $this->centres = Centre::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
});

it('creates a new user with correct data', function () {
    $validated = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'mykad_number' => '901234567890',
        'phone' => '0123456789',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant);
    $user = $result['user'];

    expect($user)->toBeInstanceOf(User::class);
    expect($result['shouldLogin'])->toBeTrue();
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect(Hash::check('password123', $user->password))->toBeTrue();
    expect($user->current_tenant_id)->toBe($this->tenant->id);
});

it('assigns Parent role to new user', function () {
    $validated = [
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'mykad_number' => '951234567890',
        'phone' => '0198765432',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant);
    $user = $result['user'];

    expect($user->hasRole('Parent'))->toBeTrue();
});

it('creates TenantUser relationship', function () {
    $validated = [
        'name' => 'Test Parent',
        'email' => 'parent@example.com',
        'password' => 'password123',
        'mykad_number' => '881234567890',
        'phone' => '0112345678',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant);
    $user = $result['user'];

    assertDatabaseHas('tenant_user', [
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
    ]);

    expect($user->tenants->contains($this->tenant))->toBeTrue();
});

it('syncs centres correctly', function () {
    $validated = [
        'name' => 'Multi Centre Parent',
        'email' => 'multicentre@example.com',
        'password' => 'password123',
        'mykad_number' => '971234567890',
        'phone' => '0187654321',
        'centre_ids' => [$this->centres[0]->id, $this->centres[1]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant);
    $user = $result['user'];

    expect($user->centres)->toHaveCount(2);
    expect($user->centres->pluck('id')->toArray())->toEqual([$this->centres[0]->id, $this->centres[1]->id]);
});

it('sets registration_step to 1', function () {
    $validated = [
        'name' => 'Step Test',
        'email' => 'steptest@example.com',
        'password' => 'password123',
        'mykad_number' => '921234567890',
        'phone' => '0123456789',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant);
    $user = $result['user'];

    expect($user->registration_step)->toBe(1);
    expect($user->getRegistrationData('step_1'))->toBeArray();
    expect($user->getRegistrationData('step_1.email'))->toBe('steptest@example.com');
});

it('handles duplicate email with firstOrCreate', function () {
    // Create an existing user without current_tenant_id
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'name' => 'Existing User',
        'current_tenant_id' => null,
    ]);

    $validated = [
        'name' => 'New Name',
        'email' => 'existing@example.com',
        'password' => 'newpassword123',
        'mykad_number' => '911234567890',
        'phone' => '0111111111',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant, $existingUser);
    $user = $result['user'];

    // Should return the existing user, not create a new one
    expect($user->id)->toBe($existingUser->id);
    expect($user->name)->toBe('New Name'); // Name gets updated

    // Count only non-factory users (excluding ones from beforeEach in other tests)
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);

    // Should update current_tenant_id for existing user
    expect($user->current_tenant_id)->toBe($this->tenant->id);
});

it('returns shouldLogin false for existing users', function () {
    // Create an existing user
    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'current_tenant_id' => null,
    ]);

    $validated = [
        'name' => 'New Name',
        'email' => 'existing@example.com',
        'password' => 'newpassword123',
        'mykad_number' => '911234567890',
        'phone' => '0111111111',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant, $existingUser);

    expect($result['shouldLogin'])->toBeFalse();
    expect($result['user']->id)->toBe($existingUser->id);
});

it('creates UserProfile with mykad and phone', function () {
    $validated = [
        'name' => 'Profile Test',
        'email' => 'profile@example.com',
        'password' => 'password123',
        'mykad_number' => '941234567890',
        'phone' => '0123456789',
        'centre_ids' => [$this->centres[0]->id],
    ];

    $result = $this->action->execute($validated, $this->tenant);
    $user = $result['user'];

    assertDatabaseHas('user_profiles', [
        'user_id' => $user->id,
        'nric' => '941234567890',
        'phone' => '0123456789',
    ]);

    expect($user->profile)->not->toBeNull();
    expect($user->profile->nric)->toBe('941234567890');
});
