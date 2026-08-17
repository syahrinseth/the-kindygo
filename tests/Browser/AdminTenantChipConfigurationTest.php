<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows an admin to view CHIP payment configuration in organisation settings', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['profile_completed' => true, 'current_tenant_id' => $tenant->id]);
    $admin->tenants()->attach($tenant->id);
    $admin->assignRole('admin');

    $this->actingAs($admin);

    visit('/admin/organisation-settings')
        ->assertSee('CHIP Payment Configuration')
        ->assertSee('Enable CHIP payments')
        ->assertNoJavascriptErrors();
});
