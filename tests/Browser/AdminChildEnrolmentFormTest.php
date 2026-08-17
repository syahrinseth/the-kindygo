<?php

use App\Models\Centre;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('uses the enrolment form to associate a child with a tenant centre', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['profile_completed' => true]);
    $admin->tenants()->attach($tenant->id);
    $admin->assignRole('admin');
    $admin->update(['current_tenant_id' => $tenant->id]);

    $child = Child::factory()->create();
    $child->addToTenant($tenant);
    Centre::factory()->forTenant($tenant)->create(['name' => 'Browser Test Centre']);

    $this->actingAs($admin);

    visit("/admin/children/{$child->id}/edit")
        ->assertSee('Enrolments')
        ->click('Create')
        ->assertSee('Enrolment Details')
        ->assertSee('Browser Test Centre')
        ->assertSee('Select a centre')
        ->assertSee('Product')
        ->assertNoJavascriptErrors();
});
