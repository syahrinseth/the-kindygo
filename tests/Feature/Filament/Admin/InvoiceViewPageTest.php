<?php

use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Filament\Admin\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAddress;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
    ]);
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->assignRole('admin');
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the payment-focused invoice statement for an administrator', function (): void {
    $parent = User::factory()->create();
    $parent->tenants()->attach($this->tenant->id);
    UserAddress::query()->create([
        'user_id' => $parent->id,
        'address' => '12 Jalan Melur',
        'city' => 'Petaling Jaya',
        'postal_code' => '47810',
        'state_code' => '10',
    ]);
    $child = Child::factory()->create([
        'first_name' => 'Sofia',
        'last_name' => 'Hana',
    ]);
    $child->tenants()->attach($this->tenant->id);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $parent->id,
        'status' => InvoiceStatus::PARTIALLY_PAID,
        'date' => now()->startOfMonth(),
        'due_at' => now()->addWeek(),
        'subtotal_amount' => 15000,
        'discount_amount' => 1000,
        'total_amount' => 14000,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'child_id' => $child->id,
        'name' => 'Monthly childcare fee',
        'price' => 15000,
        'quantity' => 1,
        'discount' => 1000,
        'effective_date' => now()->toDateString(),
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $parent->id,
        'gateway' => Gateway::CHIP,
        'amount' => 5000,
        'gateway_payment_data' => [
            'chip_data' => [
                'status' => 'paid',
                'payment_method' => 'fpx',
                'transaction_id' => 'TXN-123',
            ],
        ],
    ]);
    $payment->invoices()->attach($invoice->id, ['amount' => 5000]);

    $this->actingAs($this->admin);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertSee($invoice->number)
        ->assertSee($this->tenant->name)
        ->assertSee('Billed to')
        ->assertSee('Billing period')
        ->assertSee('Payment due')
        ->assertSee('Invoice number')
        ->assertSee('Monthly childcare fee')
        ->assertSee('Sofia Hana')
        ->assertSee('RM 150.00')
        ->assertSee('RM 140.00')
        ->assertSee('Paid to date')
        ->assertSee('Balance due')
        ->assertSee('RM 90.00')
        ->assertSee('Partially Paid')
        ->assertSee('Payment activity')
        ->assertSee('RM 50.00 received')
        ->assertSee('CHIP · FPX')
        ->assertSee('Invoice controls')
        ->assertSee('Manage line items');
});

it('shows the current e-Invoice state before and after submission', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertSee('E-Invoice')
        ->assertSee('Not submitted')
        ->assertSee('Submit when the invoice is final');

    $invoice->update([
        'einvoice_uuid' => '8d48e316-3ca3-49ae-bf4d-d1ccab50190f',
        'einvoice_status' => 'submitted',
    ]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertSee('E-Invoice')
        ->assertSee('Submitted')
        ->assertSee('E-Invoice UUID')
        ->assertSee('8d48e316-3ca3-49ae-bf4d-d1ccab50190f');
});

it('keeps line item management on the invoice edit page', function (): void {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    expect((new ViewInvoice)->getRelationManagers())->toBe([]);

    Livewire::test(EditInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertSee('Invoice details')
        ->assertSee('Invoice line items');
});

it('keeps admin controls for users who also have the parent role', function (): void {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
    $this->admin->assignRole('parent');

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertSee('Invoice controls')
        ->assertSee('Manage line items');
});
