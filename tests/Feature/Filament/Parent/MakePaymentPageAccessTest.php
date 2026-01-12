<?php

use App\Enums\InvoiceStatus;
use App\Filament\Parent\Pages\MakePayment;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('allows parent users to access make payment page', function () {
    // Create roles
    Role::create(['name' => 'Parent']);

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create parent user with completed profile
    $parent = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $parent->assignRole('Parent');
    $parent->tenants()->attach($tenant->id);

    // Authenticate first, then set tenant
    actingAs($parent);
    
    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    get('/make-payment')
        ->assertSuccessful()
        ->assertSeeLivewire(MakePayment::class);
});

it('shows empty state when parent has no unpaid invoices', function () {
    // Create roles
    Role::create(['name' => 'Parent']);

    // Create a tenant
    $tenant = Tenant::factory()->create();

    // Create parent user with completed profile
    $parent = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $parent->assignRole('Parent');
    $parent->tenants()->attach($tenant->id);

    // Authenticate first, then set tenant
    actingAs($parent);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    Livewire::test(MakePayment::class)
        ->assertSee('No Unpaid Invoices')
        ->assertSee('any outstanding invoices');
});

it('displays unpaid invoices for parent', function () {
    // Create roles
    Role::create(['name' => 'Parent']);

    // Create a tenant and centre
    $tenant = Tenant::factory()->create();
    $centre = Centre::factory()->for($tenant)->create();

    // Create parent user with completed profile
    $parent = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $parent->assignRole('Parent');
    $parent->tenants()->attach($tenant->id);

    // Create an unpaid invoice with balance
    $invoice = Invoice::factory()
        ->for($parent, 'user')
        ->for($tenant, 'tenant')
        ->for($centre, 'centre')
        ->create([
            'status' => InvoiceStatus::PENDING,
            'total' => 50000, // RM 500.00
        ]);
    
    // Create invoice item
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'total' => 50000,
        'balance_amount' => 50000,
    ]);

    // Authenticate first, then set tenant
    actingAs($parent);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

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

    // Create admin user with completed profile
    $adminUser = User::factory()->create([
        'current_tenant_id' => $tenant->id,
        'profile_completed' => true,
    ]);
    $adminUser->assignRole('Admin');
    $adminUser->tenants()->attach($tenant->id);

    // Authenticate first, then set tenant
    actingAs($adminUser);

    // Set current panel to parent
    Filament::setCurrentPanel(Filament::getPanel('parent'));
    Filament::setTenant($tenant);

    get('/make-payment')
        ->assertForbidden();
});
