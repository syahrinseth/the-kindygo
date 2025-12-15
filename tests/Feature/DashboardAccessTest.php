<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects authenticated user to root after login', function () {
    $password = 'secret-password';

    $user = User::factory()->create([
        'password' => bcrypt($password),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertRedirect('/');
});

it('dashboard is accessible to authenticated user', function () {
    $user = User::factory()->create();

    // Ensure role exists and assign so the user can access the panel
    Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Admin']);
    $user->assignRole('Admin');

    // Give the user a tenant so Filament has a valid home URL
    $tenant = \App\Models\Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'personal_tenant' => false,
        'email' => 'tenant@example.com',
    ]);

    $user->tenants()->attach($tenant->id);

    $response = $this->actingAs($user)
        ->get('/');

    // Should redirect to the Filament home URL for the user
    $response->assertRedirect(Filament\Facades\Filament::getHomeUrl());

    // Visit the Filament home URL and assert it is successful
    $this->actingAs($user)
        ->get(Filament\Facades\Filament::getHomeUrl())
        ->assertSuccessful();
});
