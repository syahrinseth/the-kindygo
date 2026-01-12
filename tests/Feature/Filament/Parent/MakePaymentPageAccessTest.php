<?php

use App\Enums\InvoiceStatus;
use App\Filament\Parent\Pages\MakePayment;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows parent users to access make payment page', function () {
    // Create roles
    Role::create(['name' => 'Parent']);

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create parent user
    $parent = User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $parent->assignRole('Parent');
    $parent->tenants()->attach($tenant->id);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    actingAs($parent)
        ->get('/parent/make-payment')
        ->assertSuccessful()
        ->assertSeeLivewire(MakePayment::class);
});

it('shows empty state when parent has no unpaid invoices', function () {
    // Create roles
    Role::create(['name' => 'Parent']);

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create parent user
    $parent = User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $parent->assignRole('Parent');
    $parent->tenants()->attach($tenant->id);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    actingAs($parent);

    Livewire::test(MakePayment::class)
        ->assertSee('No Unpaid Invoices')
        ->assertSee("You don't have any outstanding invoices");
});

it('displays unpaid invoices for parent', function () {
    // Create roles
    Role::create(['name' => 'Parent']);

    // Create a tenant and centre
    $tenant = Tenant::factory()->create();
    $centre = Centre::factory()->for($tenant)->create();

    // Create parent user
    $parent = User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $parent->assignRole('Parent');
    $parent->tenants()->attach($tenant->id);

    // Create an unpaid invoice with balance
    $invoice = Invoice::factory()
        ->for($parent, 'user')
        ->for($tenant, 'tenant')
        ->for($centre, 'centre')
        ->withItems(1)
        ->create([
            'status' => InvoiceStatus::PENDING,
            'total' => 50000, // RM 500.00
        ]);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    actingAs($parent);

    Livewire::test(MakePayment::class)
        ->assertSet('invoices', function ($invoices) {
            return count($invoices) >= 1;
        })
        ->assertSee('Select Invoices to Pay');
});

it('prevents non-parent users from accessing the page', function () {
    // Create roles
    Role::create(['name' => 'Parent']);
    Role::create(['name' => 'Admin']);

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create admin user
    $adminUser = User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $adminUser->assignRole('Admin');
    $adminUser->tenants()->attach($tenant->id);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    actingAs($adminUser)
        ->get('/parent/make-payment')
        ->assertForbidden();
});
