<?php

use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Principal', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Teacher', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Parent', 'guard_name' => 'web']);
});

it('redirects admin user to /admin after login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'profile_completed' => true,
    ]);
    $user->assignRole('Admin');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'personal_tenant' => false,
        'email' => 'tenant@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/admin');
});

it('redirects parent user to /dashboard after login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'profile_completed' => true,
    ]);
    $user->assignRole('Parent');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-parent',
        'personal_tenant' => false,
        'email' => 'tenant-parent@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
});

it('redirects admin user from root to /admin', function () {
    $user = User::factory()->create([
        'profile_completed' => true,
    ]);
    $user->assignRole('Admin');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-admin-root',
        'personal_tenant' => false,
        'email' => 'tenant-admin-root@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/admin');
});

it('parent user stays on root panel', function () {
    $user = User::factory()->create([
        'profile_completed' => true,
    ]);
    $user->assignRole('Parent');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-parent-root',
        'personal_tenant' => false,
        'email' => 'tenant-parent-root@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/dashboard');
});

it('admin user can access admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-admin-panel',
        'personal_tenant' => false,
        'email' => 'tenant-admin-panel@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('admin user can access parent panel', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    Filament::setCurrentPanel(Filament::getPanel('parent'));

    expect($user->canAccessPanel(Filament::getPanel('parent')))->toBeTrue();
});

it('parent user cannot access admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('Parent');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('parent user can access parent panel', function () {
    $user = User::factory()->create();
    $user->assignRole('Parent');

    Filament::setCurrentPanel(Filament::getPanel('parent'));

    expect($user->canAccessPanel(Filament::getPanel('parent')))->toBeTrue();
});

it('user with both admin and parent role defaults to admin panel on login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'profile_completed' => true,
    ]);
    $user->assignRole(['Admin', 'Parent']);

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-dual-role',
        'personal_tenant' => false,
        'email' => 'tenant-dual-role@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Should redirect to admin because admin role takes priority
    $response->assertRedirect('/admin');
});

it('parent user gets 403 when accessing admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('Parent');

    // Create tenant for the user
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-403',
        'personal_tenant' => false,
        'email' => 'tenant-403@example.com',
    ]);
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertForbidden();
});

it('isAdmin method returns true for admin roles', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $principal = User::factory()->create();
    $principal->assignRole('Principal');

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    expect($superAdmin->isAdmin())->toBeTrue();
    expect($admin->isAdmin())->toBeTrue();
    expect($principal->isAdmin())->toBeTrue();
    expect($teacher->isAdmin())->toBeTrue();
});

it('isAdmin method returns false for parent role', function () {
    $parent = User::factory()->create();
    $parent->assignRole('Parent');

    expect($parent->isAdmin())->toBeFalse();
});
