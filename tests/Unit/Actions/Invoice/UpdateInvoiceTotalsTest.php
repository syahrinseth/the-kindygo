<?php

use App\Actions\Invoice\UpdateInvoiceTotals;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-23');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->action = new UpdateInvoiceTotals;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('updates invoice totals with no items', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'total_items' => 5,
        'subtotal_amount' => 10000,
        'discount_amount' => 1000,
        'total_amount' => 10000,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(0)
        ->and($invoice->subtotal_amount)->toBe(0)
        ->and($invoice->discount_amount)->toBe(0)
        ->and($invoice->total_amount)->toBe(0);
});

it('updates invoice totals with multiple items', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Item 1',
        'price' => 5000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 5000,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Item 2',
        'price' => 3000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 3000,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Item 3',
        'price' => 2000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 2000,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(3)
        ->and($invoice->subtotal_amount)->toBe(10000)
        ->and($invoice->discount_amount)->toBe(0)
        ->and($invoice->total_amount)->toBe(10000);
});

it('updates invoice totals with a single item', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Test item',
        'price' => 5000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 5000,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(1)
        ->and($invoice->subtotal_amount)->toBe(5000)
        ->and($invoice->discount_amount)->toBe(0)
        ->and($invoice->total_amount)->toBe(5000);
});

it('recalculates totals when items change', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'total_items' => 10,
        'subtotal_amount' => 99999,
        'discount_amount' => 99999,
        'total_amount' => 99999,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Item 1',
        'price' => 1500,
        'quantity' => 1,
        'discount' => 0,
        'total' => 1500,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Item 2',
        'price' => 2500,
        'quantity' => 1,
        'discount' => 0,
        'total' => 2500,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(2)
        ->and($invoice->subtotal_amount)->toBe(4000)
        ->and($invoice->discount_amount)->toBe(0)
        ->and($invoice->total_amount)->toBe(4000);
});

it('calculates subtotal, discounts, and total from discounted item quantities', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'total_items' => 0,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Monthly childcare fee',
        'price' => 74000,
        'quantity' => 2,
        'discount' => 500,
        'total' => 0,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(1)
        ->and($invoice->subtotal_amount)->toBe(148000)
        ->and($invoice->discount_amount)->toBe(1000)
        ->and($invoice->total_amount)->toBe(147000);
});
