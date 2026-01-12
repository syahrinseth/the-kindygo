<?php

use App\Enums\InvoiceStatus;
use App\Filament\Parent\Pages\MakePayment;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses()->group('payment', 'checkbox');

beforeEach(function () {
    // Set up parent panel
    Filament::setCurrentPanel(Filament::getPanel('parent'));

    // Create tenant
    $this->tenant = Tenant::factory()->create();

    // Create centre
    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    // Create parent user
    $this->parent = User::factory()->create([
        'email' => 'parent@example.com',
        'current_tenant_id' => $this->tenant->id,
        'profile_completed' => true,
    ]);
    
    // Assign Parent role
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Parent']);
    $this->parent->assignRole('Parent');

    $this->parent->tenants()->attach($this->tenant->id);

    // Create unpaid invoices
    $this->invoice1 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000, // RM 100.00
    ]);

    $this->invoice2 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 20000, // RM 200.00
    ]);

    $this->invoice3 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 30000, // RM 300.00
    ]);

    $this->actingAs($this->parent);
    Filament::setTenant($this->tenant);
});

it('checks all invoices by default', function () {
    Livewire::test(MakePayment::class)
        ->assertSet('selectedInvoices.'.$this->invoice1->id, true)
        ->assertSet('selectedInvoices.'.$this->invoice2->id, true)
        ->assertSet('selectedInvoices.'.$this->invoice3->id, true);
});

it('pre-fills all invoice amounts with full balance by default', function () {
    Livewire::test(MakePayment::class)
        ->assertSet('selectedAmounts.'.$this->invoice1->id, 100.00)
        ->assertSet('selectedAmounts.'.$this->invoice2->id, 200.00)
        ->assertSet('selectedAmounts.'.$this->invoice3->id, 300.00);
});

it('calculates total correctly when all invoices are selected', function () {
    Livewire::test(MakePayment::class)
        ->assertSet('totalAmount', 60000); // RM 600.00 in cents
});

it('unchecks an invoice and clears its amount', function () {
    Livewire::test(MakePayment::class)
        ->call('toggleInvoice', $this->invoice1->id)
        ->assertSet('selectedInvoices.'.$this->invoice1->id, false)
        ->assertSet('selectedAmounts.'.$this->invoice1->id, 0)
        ->assertSet('totalAmount', 50000); // Only invoice 2 & 3 (RM 500.00)
});

it('checks an invoice and pre-fills its amount', function () {
    Livewire::test(MakePayment::class)
        ->call('toggleInvoice', $this->invoice1->id) // Uncheck
        ->assertSet('selectedInvoices.'.$this->invoice1->id, false)
        ->assertSet('selectedAmounts.'.$this->invoice1->id, 0)
        ->call('toggleInvoice', $this->invoice1->id) // Check again
        ->assertSet('selectedInvoices.'.$this->invoice1->id, true)
        ->assertSet('selectedAmounts.'.$this->invoice1->id, 100.00)
        ->assertSet('totalAmount', 60000); // Back to full total
});

it('only includes selected invoices in total calculation', function () {
    Livewire::test(MakePayment::class)
        ->call('toggleInvoice', $this->invoice1->id) // Uncheck invoice 1
        ->call('toggleInvoice', $this->invoice2->id) // Uncheck invoice 2
        ->assertSet('selectedInvoices.'.$this->invoice1->id, false)
        ->assertSet('selectedInvoices.'.$this->invoice2->id, false)
        ->assertSet('selectedInvoices.'.$this->invoice3->id, true)
        ->assertSet('totalAmount', 30000); // Only invoice 3 (RM 300.00)
});

it('allows custom amounts only for selected invoices', function () {
    Livewire::test(MakePayment::class)
        ->set('selectedAmounts.'.$this->invoice1->id, 50.00) // Half of invoice 1
        ->call('calculateTotal')
        ->assertSet('totalAmount', 55000); // 50 + 200 + 300 = RM 550.00
});

it('does not count unchecked invoices even if they have amounts', function () {
    Livewire::test(MakePayment::class)
        ->set('selectedAmounts.'.$this->invoice1->id, 50.00)
        ->call('toggleInvoice', $this->invoice1->id) // Uncheck
        ->assertSet('selectedInvoices.'.$this->invoice1->id, false)
        ->assertSet('totalAmount', 50000); // Only invoice 2 & 3, not invoice 1
});

it('shows validation error when no invoices are selected', function () {
    Livewire::test(MakePayment::class)
        ->call('toggleInvoice', $this->invoice1->id) // Uncheck all
        ->call('toggleInvoice', $this->invoice2->id)
        ->call('toggleInvoice', $this->invoice3->id)
        ->assertSet('totalAmount', 0)
        ->call('processPayment')
        ->assertNotified();
});

it('recalculates total when amount is updated for selected invoice', function () {
    Livewire::test(MakePayment::class)
        ->set('selectedAmounts.'.$this->invoice1->id, 25.00) // Change from 100 to 25
        ->call('calculateTotal')
        ->assertSet('totalAmount', 52500); // 25 + 200 + 300 = RM 525.00
});
