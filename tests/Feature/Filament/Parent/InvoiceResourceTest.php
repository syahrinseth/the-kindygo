<?php

use App\Enums\InvoiceStatus;
use App\Filament\Parent\Resources\InvoiceResource;
use App\Filament\Parent\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Parent\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create roles
    Role::firstOrCreate(['name' => 'Parent']);

    $this->parent = User::factory()->create();
    $this->parent->tenants()->attach($this->tenant->id);
    $this->parent->assignRole('Parent');
    $this->parent->update(['current_tenant_id' => $this->tenant->id]);

    $this->otherParent = User::factory()->create();
    $this->otherParent->tenants()->attach($this->tenant->id);
    $this->otherParent->assignRole('Parent');
    $this->otherParent->update(['current_tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('parent'));
});

test('parent can view their own invoices list', function () {
    $this->actingAs($this->parent);

    $ownInvoices = Invoice::factory()->count(3)->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    // Create invoice items for each invoice
    foreach ($ownInvoices as $invoice) {
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
    }

    $otherInvoices = Invoice::factory()->count(2)->create([
        'user_id' => $this->otherParent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($ownInvoices)
        ->assertCanNotSeeTableRecords($otherInvoices);
});

test('parent can view individual invoice details', function () {
    $this->actingAs($this->parent);

    $invoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertSee($invoice->number);
});

test('parent cannot view other parents invoices', function () {
    $this->actingAs($this->parent);

    $otherInvoice = Invoice::factory()->create([
        'user_id' => $this->otherParent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    // Expect ModelNotFoundException because the invoice is filtered out at the query level
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    Livewire::test(ViewInvoice::class, ['record' => $otherInvoice->id]);
});

test('parent can see download pdf action for invoices', function () {
    $this->actingAs($this->parent);

    $invoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertActionVisible('download_pdf');
});

test('parent can see make payment action for unpaid invoices', function () {
    $this->actingAs($this->parent);

    $invoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000, // RM100.00
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertActionVisible('make_payment');
});

test('parent can see make payment action visibility based on balance', function () {
    $this->actingAs($this->parent);

    // Invoice with balance > 0 should show make payment button
    $invoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000, // RM100.00
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

    // Should see make payment action since balance > 0
    Livewire::test(ViewInvoice::class, ['record' => $invoice->id])
        ->assertSuccessful()
        ->assertActionVisible('make_payment');
});

test('parent can filter invoices by status', function () {
    $this->actingAs($this->parent);

    $paidInvoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PAID,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $paidInvoice->id]);

    $pendingInvoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $pendingInvoice->id]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->filterTable('status', InvoiceStatus::PAID->value)
        ->assertCanSeeTableRecords([$paidInvoice])
        ->assertCanNotSeeTableRecords([$pendingInvoice]);
});

test('parent can filter invoices by centre', function () {
    $this->actingAs($this->parent);

    $centre2 = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $invoiceCentre1 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoiceCentre1->id]);

    $invoiceCentre2 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre2->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoiceCentre2->id]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->filterTable('centre', [$this->centre->id])
        ->assertCanSeeTableRecords([$invoiceCentre1])
        ->assertCanNotSeeTableRecords([$invoiceCentre2]);
});

test('parent can filter overdue invoices', function () {
    $this->actingAs($this->parent);

    $overdueInvoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::OVERDUE,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $overdueInvoice->id]);

    $pendingInvoice = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'due_at' => now()->addDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $pendingInvoice->id]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->filterTable('overdue')
        ->assertCanSeeTableRecords([$overdueInvoice])
        ->assertCanNotSeeTableRecords([$pendingInvoice]);
});

test('parent can search invoices by invoice number', function () {
    $this->actingAs($this->parent);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'number' => 'INV-12345',
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice1->id]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'number' => 'INV-67890',
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice2->id]);

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->searchTable('INV-12345')
        ->assertCanSeeTableRecords([$invoice1])
        ->assertCanNotSeeTableRecords([$invoice2]);
});

test('invoice resource displays navigation badge with unpaid count', function () {
    $this->actingAs($this->parent);

    Invoice::factory()->count(2)->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PAID,
    ]);

    $badge = InvoiceResource::getNavigationBadge();

    expect($badge)->toBe('2');
});

test('invoice resource navigation badge is danger color when overdue exist', function () {
    $this->actingAs($this->parent);

    Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $badgeColor = InvoiceResource::getNavigationBadgeColor();

    expect($badgeColor)->toBe('danger');
});

test('invoice resource navigation badge is warning color when no overdue', function () {
    $this->actingAs($this->parent);

    Invoice::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $badgeColor = InvoiceResource::getNavigationBadgeColor();

    expect($badgeColor)->toBe('warning');
});

test('parent cannot access admin invoice routes', function () {
    $this->actingAs($this->parent);

    $response = $this->get('/admin/invoices/invoices');

    $response->assertForbidden();
});

test('invoice resource is only visible to parents', function () {
    $this->actingAs($this->parent);

    expect(InvoiceResource::canViewAny())->toBeTrue();
    expect(InvoiceResource::shouldRegisterNavigation())->toBeTrue();
});

test('parent cannot create invoices through resource', function () {
    $this->actingAs($this->parent);

    expect(InvoiceResource::canCreate())->toBeFalse();
});
