<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\Centre;
use App\Models\User;
use App\Actions\Invoice\UpdateInvoiceTotals;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-23');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->action = new UpdateInvoiceTotals();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('updates invoice totals with no items', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'total_items' => 5,
        'total_amount' => 10000,
        'total' => 10000,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(0)
        ->and($invoice->total_amount)->toBe(0)
        ->and($invoice->total)->toBe(0);
});

it('updates invoice totals with single item', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'name' => 'Test Item',
        'price' => 5000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 5000,
    ]);

    $this->action->execute($invoice);

    $invoice->refresh();
    expect($invoice->total_items)->toBe(1)
        ->and($invoice->total_amount)->toBe(5000)
        ->and($invoice->total)->toBe(5000);
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
        ->and($invoice->total_amount)->toBe(10000)
        ->and($invoice->total)->toBe(10000);
});

it('recalculates totals correctly when items change', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'total_items' => 10,
        'total_amount' => 99999,
        'total' => 99999,
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
        ->and($invoice->total_amount)->toBe(4000)
        ->and($invoice->total)->toBe(4000);
});
