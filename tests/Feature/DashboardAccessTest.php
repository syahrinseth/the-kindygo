<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
});

it('redirects authenticated admin user to /admin after login', function () {
    $password = 'secret-password';

    $user = User::factory()->create([
        'password' => bcrypt($password),
        'profile_completed' => true,
    ]);
    $user->assignRole('Admin');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-dash',
        'personal_tenant' => false,
        'email' => 'tenant-dash@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertRedirect('/admin');
});

it('redirects authenticated parent user to /dashboard after login', function () {
    $password = 'secret-password';

    $user = User::factory()->create([
        'password' => bcrypt($password),
        'profile_completed' => true,
    ]);
    $user->assignRole('Parent');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-dash-parent',
        'personal_tenant' => false,
        'email' => 'tenant-dash-parent@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertRedirect('/dashboard');
});

it('admin panel is accessible to authenticated admin user', function () {
    $user = User::factory()->create([
        'profile_completed' => true, // Mark profile as completed
    ]);
    $user->assignRole('Admin');

    // Give the user a tenant so Filament has a valid home URL
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-admin-access',
        'personal_tenant' => false,
        'email' => 'tenant-admin-access@example.com',
    ]);

    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->get('/');

    // Admin users should redirect to the admin panel
    $response->assertRedirect('/admin');

    // Visit the Filament admin dashboard URL (follows redirect from /admin)
    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertSuccessful();
});

it('parent panel is accessible to authenticated parent user', function () {
    $user = User::factory()->create([
        'profile_completed' => true, // Mark profile as completed
    ]);
    $user->assignRole('Parent');

    // Give the user a tenant so Filament has a valid home URL
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-parent-access',
        'personal_tenant' => false,
        'email' => 'tenant-parent-access@example.com',
    ]);

    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->get('/');

    // Parent users should redirect to the dashboard
    $response->assertRedirect('/dashboard');

    // Visit the Filament parent dashboard URL and assert it is successful
    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSuccessful();
});
