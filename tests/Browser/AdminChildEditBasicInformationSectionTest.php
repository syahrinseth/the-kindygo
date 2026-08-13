<?php

use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows the basic information section on the admin child edit page', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['profile_completed' => true]);
    $admin->assignRole('admin');
    $admin->tenants()->attach($tenant->id);
    $admin->update(['current_tenant_id' => $tenant->id]);

    $child = Child::factory()->create();
    $child->addToTenant($tenant);

    $this->actingAs($admin);

    visit("/admin/children/{$child->id}/edit")
        ->assertSeeIn('.fi-section-header-heading', 'Basic Information')
        ->assertSee('First Name')
        ->assertNoJavascriptErrors();
});
