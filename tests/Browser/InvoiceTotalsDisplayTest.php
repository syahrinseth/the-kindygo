<?php

use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('labels the pre-discount invoice amount as subtotal in the admin list', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create(['current_tenant_id' => $tenant->id, 'profile_completed' => true]);
    $admin->tenants()->attach($tenant->id);
    $admin->assignRole('admin');
    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'user_id' => $admin->id,
        'status' => InvoiceStatus::PENDING,
        'subtotal_amount' => 148000,
        'discount_amount' => 0,
        'total_amount' => 148000,
    ]);

    $this->actingAs($admin);

    visit('/admin/invoices')
        ->assertSee('Subtotal')
        ->assertSee('MYR 1,480.00')
        ->assertNoJavascriptErrors();
});
