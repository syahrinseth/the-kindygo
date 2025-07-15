<?php

use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

test('parent with incomplete profile is redirected to profile completion', function () {
    // Create a tenant
    $tenant = Tenant::factory()->create();
    
    // Create Parent role
    $parentRole = Role::create(['name' => 'Parent']);
    
    // Create a user with Parent role and incomplete profile
    $user = User::factory()->create([
        'profile_completed' => false,
    ]);
    $user->assignRole($parentRole);
    $tenant->addUser($user);
    
    // Try to access the app
    $response = $this->actingAs($user)->get('/app');
    
    // Should be redirected to profile completion
    $response->assertRedirect('/profile/complete');
});

test('parent with complete profile can access profile page', function () {
    // Create a tenant
    $tenant = Tenant::factory()->create();
    
    // Create Parent role
    $parentRole = Role::create(['name' => 'Parent']);
    
    // Create a user with Parent role and complete profile
    $user = User::factory()->create([
        'profile_completed' => true,
        'nric' => '123456789012',
        'address' => '123 Test Street',
        'city' => 'Test City',
        'postal_code' => '12345',
        'state_code' => 'SGR',
    ]);
    $user->assignRole($parentRole);
    $tenant->addUser($user);
    
    // Try to access the profile completion page
    $response = $this->actingAs($user)->get('/profile/complete');
    
    // Should be redirected to app since profile is already completed
    $response->assertRedirect('/app');
});

test('non parent user is not redirected', function () {
    // Create a tenant
    $tenant = Tenant::factory()->create();
    
    // Create Admin role
    $adminRole = Role::create(['name' => 'Admin']);
    
    // Create a user with Admin role and incomplete profile
    $user = User::factory()->create([
        'profile_completed' => false,
    ]);
    $user->assignRole($adminRole);
    $tenant->addUser($user);
    
    // Try to access profile completion page
    $response = $this->actingAs($user)->get('/profile/complete');
    
    // Should be redirected to app since not a parent
    $response->assertRedirect('/app');
});
