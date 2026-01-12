<?php

use App\Actions\Ledger\CreateInvoiceItemLedgerEntryAction;
use App\Enums\ProductPriority;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    test()->tenant = Tenant::factory()->create();
    test()->user = User::factory()->create(['current_tenant_id' => test()->tenant->id]);
    test()->tenant->users()->attach(test()->user->id);
    test()->actingAs(test()->user);

    test()->centre = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->child = Child::factory()->create();
    test()->child->tenants()->attach(test()->tenant->id);

    test()->action = new CreateInvoiceItemLedgerEntryAction;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates initial debit ledger entry for invoice item', function () {
    $product = Product::factory()->create([
        'tenant_id' => test()->tenant->id,
        'priority' => ProductPriority::HIGH,
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'date' => now()->subDays(5),
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Monthly Fee',
        'description' => 'January 2026 Monthly Fee',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger)->toBeInstanceOf(InvoiceItemsLedger::class)
        ->and($ledger->tenant_id)->toBe(test()->tenant->id)
        ->and($ledger->user_id)->toBe(test()->user->id)
        ->and($ledger->centre_id)->toBe(test()->centre->id)
        ->and($ledger->invoice_id)->toBe($invoice->id)
        ->and($ledger->invoice_item_id)->toBe($item->id)
        ->and($ledger->child_id)->toBe(test()->child->id)
        ->and($ledger->product_id)->toBe($product->id)
        ->and($ledger->ledger_type)->toBe('invoice_item')
        ->and($ledger->debit_amount)->toBe(30000)
        ->and($ledger->credit_amount)->toBe(0)
        ->and($ledger->balance_amount)->toBe(30000)
        ->and($ledger->paid)->toBeFalse()
        ->and($ledger->priority)->toBe(ProductPriority::HIGH->value)
        ->and($ledger->payment_id)->toBeNull();
});

it('uses product priority when available', function () {
    $product = Product::factory()->create([
        'tenant_id' => test()->tenant->id,
        'priority' => ProductPriority::CRITICAL,
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Critical Item',
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
        'paid_amount' => 0,
        'balance_amount' => 50000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->priority)->toBe(ProductPriority::CRITICAL->value);
});

it('defaults to MEDIUM priority when product is null', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => null,
        'name' => 'Manual Item',
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
        'paid_amount' => 0,
        'balance_amount' => 20000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', null);

    $ledger = test()->action->execute($item);

    expect($ledger->priority)->toBe(2); // MEDIUM default
});

it('uses LOW priority when product has LOW priority', function () {
    $product = Product::factory()->create([
        'tenant_id' => test()->tenant->id,
        'priority' => 1, // LOW priority integer value
    ]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Standard Item',
        'price' => 25000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 25000,
        'paid_amount' => 0,
        'balance_amount' => 25000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->priority)->toBe(1); // LOW priority
});

it('stores reference data with invoice number and item type', function () {
    $product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'number' => 'INV-2026-001',
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    $referenceData = $ledger->reference_data;

    expect($referenceData)->toHaveKey('invoice_number')
        ->and($referenceData['invoice_number'])->toBe('INV-2026-001')
        ->and($referenceData['item_type'])->toBe('initial_invoice_item')
        ->and($referenceData['created_via'])->toBe('invoice_item_created');
});

it('uses item description when available', function () {
    $product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Short Name',
        'description' => 'This is a detailed description of the item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->description)->toBe('This is a detailed description of the item');
});

it('falls back to item name when description is null', function () {
    $product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Item Name Only',
        'description' => null,
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->description)->toBe('Item Name Only');
});

it('uses invoice date for recorded_at', function () {
    $product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    $invoiceDate = Carbon::parse('2026-01-01');
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'date' => $invoiceDate,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->recorded_at->toDateString())->toBe('2026-01-01');
});

it('uses invoice date for recorded_at timestamp', function () {
    $product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    $specificDate = Carbon::parse('2026-01-15');
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'date' => $specificDate,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->recorded_at->toDateString())->toBe('2026-01-15');
});

it('records full balance as outstanding initially', function () {
    $product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
    ]);

    $item = new InvoiceItem([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => $product->id,
        'name' => 'Test Item',
        'price' => 75000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 75000,
        'paid_amount' => 0,
        'balance_amount' => 75000,
        'paid' => false,
    ]);
    $item->setRelation('invoice', $invoice);
    $item->setRelation('product', $product);

    $ledger = test()->action->execute($item);

    expect($ledger->debit_amount)->toBe(75000)
        ->and($ledger->credit_amount)->toBe(0)
        ->and($ledger->balance_amount)->toBe(75000)
        ->and($ledger->paid)->toBeFalse();
});
