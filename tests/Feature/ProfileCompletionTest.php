<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserProfile;
use Spatie\Permission\Models\Role;

test('parent with incomplete profile is redirected to profile completion', function () {
    /** @var Tests\TestCase $this */
    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create Parent role
    $parentRole = Role::create(['name' => 'parent']);

    // Create a user with Parent role and incomplete profile
    /** @var User $user */
    $user = User::factory()->create([
        'profile_completed' => false,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole($parentRole);
    $tenant->addUser($user);

    // Try to access the parent dashboard
    $response = $this->actingAs($user)->get(route('filament.parent.pages.dashboard'));

    // Should be redirected to profile completion
    $response->assertRedirect();
});

test('parent with complete profile can access profile page', function () {
    /**
     * @var Tests\TestCase $this
     */

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create Parent role
    $parentRole = Role::create(['name' => 'parent']);

    // Create a user with Parent role and complete profile
    /** @var User $user */
    $user = User::factory()->create([
        'profile_completed' => true,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole($parentRole);
    $tenant->addUser($user);

    // Create user profile and address
    UserProfile::create([
        'user_id' => $user->id,
        'nric' => '123456789012',
    ]);
    UserAddress::create([
        'user_id' => $user->id,
        'address' => '123 Test Street',
        'city' => 'Test City',
        'postal_code' => '12345',
        'state_code' => '10',
    ]);

    // Try to access the profile completion page
    $response = $this->actingAs($user)->get('/profile/complete');

    // Should be redirected to the parent panel since profile is already completed
    $response->assertRedirect(route('filament.parent.pages.dashboard'));
});

test('non parent user is not redirected', function () {
    /** @var Tests\TestCase $this */

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create Admin role
    $adminRole = Role::create(['name' => 'admin']);

    // Create a user with Admin role and incomplete profile
    /** @var User $user */
    $user = User::factory()->create([
        'profile_completed' => false,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole($adminRole);
    $tenant->addUser($user);

    // Try to access profile completion page
    $response = $this->actingAs($user)->get('/profile/complete');

    // Should be able to access the page since middleware skips non-parents
    $response->assertSuccessful();
});
