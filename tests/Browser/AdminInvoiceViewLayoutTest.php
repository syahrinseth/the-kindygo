<?php

use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('shows the document-style invoice alongside its operational details', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $admin->tenants()->attach($tenant->id);
    $admin->assignRole('admin');

    $parent = User::factory()->create();
    $parent->tenants()->attach($tenant->id);
    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'user_id' => $parent->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 12000,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $parent->id,
        'gateway' => Gateway::CASH,
        'amount' => 2000,
    ]);
    $payment->invoices()->attach($invoice->id, ['amount' => 2000]);

    $this->actingAs($admin);

    visit("/admin/invoices/{$invoice->id}")
        ->assertSee($invoice->number)
        ->assertVisible('.invoice-view-grid')
        ->assertVisible('.invoice-paper')
        ->assertVisible('.invoice-operations')
        ->assertSee('Invoice total')
        ->assertSee('Balance due')
        ->assertSee('RM 100.00')
        ->assertSee('Payment activity')
        ->assertSee('RM 20.00 received')
        ->assertNoJavascriptErrors()
        ->click('Manage line items')
        ->assertPathIs("/admin/invoices/{$invoice->id}/edit")
        ->assertQueryStringHas('relation', '0')
        ->assertSee('Invoice line items')
        ->assertNoJavascriptErrors();
});

it('keeps the invoice readable on a mobile viewport', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $admin->tenants()->attach($tenant->id);
    $admin->assignRole('admin');

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'user_id' => $admin->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 12000,
    ]);

    $this->actingAs($admin);

    visit("/admin/invoices/{$invoice->id}")
        ->on()->mobile()
        ->assertVisible('.invoice-paper')
        ->assertVisible('.invoice-balance')
        ->assertVisible('.invoice-operations')
        ->assertSee('Manage line items')
        ->assertNoJavascriptErrors();
});

it('uses a high-contrast invoice canvas in dark mode', function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $admin->tenants()->attach($tenant->id);
    $admin->assignRole('admin');

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'user_id' => $admin->id,
        'status' => InvoiceStatus::PENDING,
        'due_at' => now()->addWeek(),
        'total_amount' => 12000,
    ]);

    $this->actingAs($admin);

    $page = visit("/admin/invoices/{$invoice->id}");
    $page->script("localStorage.setItem('theme', 'dark')");
    $page->refresh()
        ->assertVisible('.invoice-paper')
        ->assertVisible('.invoice-balance')
        ->assertNoJavascriptErrors();

    expect($page->script("document.documentElement.classList.contains('dark')"))->toBeTrue()
        ->and($page->script("getComputedStyle(document.querySelector('.invoice-paper')).backgroundColor"))
        ->toBe('rgb(17, 24, 39)')
        ->and($page->script("getComputedStyle(document.querySelector('.invoice-paper')).color"))
        ->toBe('rgb(226, 232, 240)')
        ->and($page->script("getComputedStyle(document.querySelector('.invoice-balance')).backgroundColor"))
        ->toBe('rgb(69, 26, 3)');
});
