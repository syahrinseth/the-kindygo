<?php

use App\Enums\ProductStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Product;
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
    $centre = Centre::factory()->forTenant($tenant)->create(['name' => 'Browser Test Centre']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Browser Test Product',
        'status' => ProductStatus::ACTIVE->value,
    ]);
    $product->centres()->attach($centre->id);

    $this->actingAs($admin);

    visit("/admin/children/{$child->id}/edit")
        ->assertSee('Enrolments')
        ->click('New child enrolment')
        ->assertSee('Enrolment Details')
        ->assertSee('Centre')
        ->assertSee('Select a centre')
        ->assertSee('Product')
        ->assertSee('Status')
        ->assertSee('Type')
        ->assertSee('Billing & Schedule')
        ->assertSee('Billing Frequency')
        ->assertSee('Start Date')
        ->assertSee('End Date')
        ->assertSee('Additional Products')
        ->assertSee('Add additional products to this enrolment with their own billing frequencies')
        ->assertNoJavascriptErrors();
});
