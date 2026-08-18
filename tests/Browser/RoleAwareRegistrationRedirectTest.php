<?php

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('lands an incomplete super admin on the admin dashboard', function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'profile_completed' => true,
        'registration_step' => 4,
    ]);
    $tenant = Tenant::factory()->create([
        'user_id' => $user->id,
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
    ]);

    $user->assignRole('super-admin');
    $user->tenants()->attach($tenant->id);
    $user->update(['current_tenant_id' => $tenant->id]);

    $this->actingAs($user);

    visit('/')
        ->assertPathIs('/admin/dashboard')
        ->assertSee('Dashboard')
        ->assertNoJavascriptErrors();
});
