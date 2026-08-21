<?php

use App\Actions\Quotation\ConvertQuotationToInvoice;
use App\Enums\QuotationStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->quotation = Quotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'child_id' => $this->child->id,
        'status' => QuotationStatus::ACCEPTED,
        'date' => Carbon::parse('2026-01-08'),
    ]);

    $this->item1 = QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'child_id' => $this->child->id,
        'name' => 'Monthly Tuition',
        'description' => 'February 2026 tuition',
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
        'period_start' => Carbon::parse('2026-02-01'),
        'period_end' => Carbon::parse('2026-02-28'),
    ]);

    $this->item2 = QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'child_id' => $this->child->id,
        'name' => 'Registration Fee',
        'description' => 'One-time registration',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
    ]);

    $this->item3 = QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'child_id' => $this->child->id,
        'name' => 'Activity Materials',
        'description' => 'Art supplies',
        'price' => 5000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 5000,
    ]);

    $this->action = app(ConvertQuotationToInvoice::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates invoice with selected items only', function () {
    $selectedItemIds = [$this->item1->id, $this->item2->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->invoiceItems)->toHaveCount(2);

    $itemNames = $invoice->invoiceItems->pluck('name')->toArray();
    expect($itemNames)->toContain('Monthly Tuition')
        ->and($itemNames)->toContain('Registration Fee')
        ->and($itemNames)->not->toContain('Activity Materials');
});

it('marks quotation as CONVERTED', function () {
    $selectedItemIds = [$this->item1->id];

    $this->action->execute($this->quotation, $selectedItemIds);
    $this->quotation->refresh();

    expect($this->quotation->status)->toBe(QuotationStatus::CONVERTED);
});

it('stores converted_invoice_id', function () {
    $selectedItemIds = [$this->item1->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);
    $this->quotation->refresh();

    expect($this->quotation->converted_invoice_id)->toBe($invoice->id);
});

it('copies item attributes correctly', function () {
    $selectedItemIds = [$this->item1->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);
    $invoiceItem = $invoice->invoiceItems->first();

    expect($invoiceItem->child_id)->toBe($this->item1->child_id)
        ->and($invoiceItem->name)->toBe($this->item1->name)
        ->and($invoiceItem->description)->toBe($this->item1->description)
        ->and($invoiceItem->price)->toBe($this->item1->price)
        ->and($invoiceItem->quantity)->toBe($this->item1->quantity)
        ->and($invoiceItem->discount)->toBe($this->item1->discount)
        ->and($invoiceItem->total)->toBe($this->item1->total)
        ->and($invoiceItem->period_start?->toDateString())->toBe('2026-02-01')
        ->and($invoiceItem->period_end?->toDateString())->toBe('2026-02-28');
});

it('sets invoice due date to 7 days from today', function () {
    $selectedItemIds = [$this->item1->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    expect($invoice->due_at->toDateString())->toBe('2026-01-15'); // 7 days from 2026-01-08
});

it('excludes non-selected items', function () {
    $selectedItemIds = [$this->item1->id]; // Only select item1

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    expect($invoice->invoiceItems)->toHaveCount(1);
    expect($invoice->invoiceItems->first()->name)->toBe('Monthly Tuition');

    // item2 and item3 should not be converted
    assertDatabaseHas(InvoiceItem::class, [
        'invoice_id' => $invoice->id,
        'name' => 'Monthly Tuition',
    ]);

    // Check that other items were NOT created in invoice_items
    expect(InvoiceItem::where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('updates invoice totals after conversion', function () {
    $selectedItemIds = [$this->item1->id, $this->item2->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    // Total should be sum of selected items
    expect($invoice->total_items)->toBe(2)
        ->and($invoice->total_amount)->toBe(60000) // 50000 + 10000
        ->and($invoice->total_amount)->toBe(60000);
});

it('creates invoice with correct tenant and centre', function () {
    $selectedItemIds = [$this->item1->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    expect($invoice->tenant_id)->toBe($this->tenant->id)
        ->and($invoice->centre_id)->toBe($this->centre->id)
        ->and($invoice->user_id)->toBe($this->user->id);
});

it('persists invoice to database', function () {
    $selectedItemIds = [$this->item1->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    assertDatabaseHas(Invoice::class, [
        'id' => $invoice->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
    ]);

    assertDatabaseHas(InvoiceItem::class, [
        'invoice_id' => $invoice->id,
        'name' => 'Monthly Tuition',
        'price' => 50000,
        'total' => 50000,
    ]);
});

it('handles items with discounts', function () {
    $this->item1->update(['discount' => 5000, 'total' => 45000]);
    $selectedItemIds = [$this->item1->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);
    $invoiceItem = $invoice->invoiceItems->first();

    expect($invoiceItem->discount)->toBe(5000)
        ->and($invoiceItem->total)->toBe(45000);
});

it('converts all items when all selected', function () {
    $selectedItemIds = [$this->item1->id, $this->item2->id, $this->item3->id];

    $invoice = $this->action->execute($this->quotation, $selectedItemIds);

    expect($invoice->invoiceItems)->toHaveCount(3)
        ->and($invoice->total_amount)->toBe(65000); // 50000 + 10000 + 5000
});
