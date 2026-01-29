<?php

use App\Actions\Payment\RecordLedgerEntriesAction;
use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;
use App\Models\Payment;
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
    test()->product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    test()->action = new RecordLedgerEntriesAction;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates credit ledger entry for fully paid invoice item', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 30000, // Fully paid
        'balance_amount' => 0,
        'paid' => true,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 30000,
        'gateway' => Gateway::BANK_TRANSFER,
        'reference_no' => 'BT123',
        'status' => PaymentStatus::PAID,
    ]);

    // Attach invoice to payment
    $payment->invoices()->attach($invoice->id, ['amount' => 30000]);

    $allocationSummary = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 30000,
                'fully_paid' => true,
            ],
        ],
    ];

    // Get initial ledger count (1 debit from observer)
    $initialCount = InvoiceItemsLedger::count();

    test()->action->execute($payment, $allocationSummary);

    // Should have created 1 new credit entry
    expect(InvoiceItemsLedger::count())->toBe($initialCount + 1);

    $creditEntry = InvoiceItemsLedger::where('invoice_item_id', $item->id)
        ->where('payment_id', $payment->id)
        ->first();

    expect($creditEntry)->not->toBeNull()
        ->and($creditEntry->credit_amount)->toBe(30000)
        ->and($creditEntry->debit_amount)->toBe(0)
        ->and($creditEntry->balance_amount)->toBe(0)
        ->and($creditEntry->paid)->toBeTrue()
        ->and($creditEntry->payment_id)->toBe($payment->id);
});

it('creates credit ledger entry for partially paid invoice item', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 15000, // Partially paid
        'balance_amount' => 15000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 15000,
        'status' => PaymentStatus::PAID,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 15000]);

    $allocationSummary = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 15000,
                'fully_paid' => false,
            ],
        ],
    ];

    $initialCount = InvoiceItemsLedger::count();

    test()->action->execute($payment, $allocationSummary);

    expect(InvoiceItemsLedger::count())->toBe($initialCount + 1);

    $creditEntry = InvoiceItemsLedger::where('invoice_item_id', $item->id)
        ->where('payment_id', $payment->id)
        ->first();

    expect($creditEntry)->not->toBeNull()
        ->and($creditEntry->credit_amount)->toBe(15000)
        ->and($creditEntry->balance_amount)->toBe(15000)
        ->and($creditEntry->paid)->toBeFalse();
});

it('stores payment metadata in reference_data', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 30000,
        'balance_amount' => 0,
        'paid' => true,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 30000,
        'gateway' => Gateway::CHIP,
        'reference_no' => 'CHIP-12345',
        'status' => PaymentStatus::PAID,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 30000]);

    $allocationSummary = [
        'strategy' => 'user_defined_priority',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 30000,
                'fully_paid' => true,
            ],
        ],
    ];

    test()->action->execute($payment, $allocationSummary);

    $creditEntry = InvoiceItemsLedger::where('invoice_item_id', $item->id)
        ->where('payment_id', $payment->id)
        ->first();

    $referenceData = $creditEntry->reference_data; // Already cast to array

    expect($referenceData)->toHaveKey('payment_id')
        ->and($referenceData['payment_id'])->toBe($payment->id)
        ->and($referenceData['gateway'])->toBe('chip')
        ->and($referenceData['reference_no'])->toBe('CHIP-12345')
        ->and($referenceData['strategy'])->toBe('user_defined_priority')
        ->and($referenceData['fully_paid'])->toBeTrue()
        ->and($referenceData['payment_status'])->toBe('paid');
});

it('uses bulk insert for performance with multiple items', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 90000,
    ]);

    // Create 3 items
    $items = [];
    for ($i = 1; $i <= 3; $i++) {
        $items[] = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'child_id' => test()->child->id,
            'product_id' => test()->product->id,
            'name' => "Test Item {$i}",
            'price' => 30000,
            'quantity' => 1,
            'discount' => 0,
            'total' => 30000,
            'paid_amount' => 30000,
            'balance_amount' => 0,
            'paid' => true,
        ]);
    }

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 90000,
        'status' => PaymentStatus::PAID,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 90000]);

    $allocationSummary = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 90000,
                'fully_paid' => true,
            ],
        ],
    ];

    $initialCount = InvoiceItemsLedger::count();

    test()->action->execute($payment, $allocationSummary);

    // Should have created 3 credit entries (one per item)
    expect(InvoiceItemsLedger::count())->toBe($initialCount + 3);

    // Verify all entries have payment_id
    $creditEntries = InvoiceItemsLedger::where('payment_id', $payment->id)->get();
    expect($creditEntries)->toHaveCount(3);
});

it('does not create entries for items with zero paid amount', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 60000,
    ]);

    // Item 1: Paid
    $paidItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Paid Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 30000,
        'balance_amount' => 0,
        'paid' => true,
    ]);

    // Item 2: Unpaid
    $unpaidItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Unpaid Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 30000,
        'status' => PaymentStatus::PAID,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 30000]);

    $allocationSummary = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 30000,
                'fully_paid' => false,
            ],
        ],
    ];

    test()->action->execute($payment, $allocationSummary);

    // Should only create entry for paid item
    $paidItemEntry = InvoiceItemsLedger::where('invoice_item_id', $paidItem->id)
        ->where('payment_id', $payment->id)
        ->first();

    $unpaidItemEntry = InvoiceItemsLedger::where('invoice_item_id', $unpaidItem->id)
        ->where('payment_id', $payment->id)
        ->first();

    expect($paidItemEntry)->not->toBeNull()
        ->and($unpaidItemEntry)->toBeNull();
});

it('calculates credit amount as delta from previous balance', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    // First payment - pay 10000
    $payment1 = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PAID,
    ]);
    $payment1->invoices()->attach($invoice->id, ['amount' => 10000]);

    $item->update([
        'paid_amount' => 10000,
        'balance_amount' => 20000,
    ]);

    $allocationSummary1 = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 10000,
                'fully_paid' => false,
            ],
        ],
    ];

    test()->action->execute($payment1, $allocationSummary1);

    // Second payment - pay another 20000
    $payment2 = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 20000,
        'status' => PaymentStatus::PAID,
    ]);
    $payment2->invoices()->attach($invoice->id, ['amount' => 20000]);

    $item->update([
        'paid_amount' => 30000,
        'balance_amount' => 0,
        'paid' => true,
    ]);

    $allocationSummary2 = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 20000,
                'fully_paid' => true,
            ],
        ],
    ];

    test()->action->execute($payment2, $allocationSummary2);

    // Check first payment entry
    $entry1 = InvoiceItemsLedger::where('payment_id', $payment1->id)->first();
    expect($entry1->credit_amount)->toBe(10000)
        ->and($entry1->balance_amount)->toBe(20000);

    // Check second payment entry
    $entry2 = InvoiceItemsLedger::where('payment_id', $payment2->id)->first();
    expect($entry2->credit_amount)->toBe(20000)
        ->and($entry2->balance_amount)->toBe(0);
});

it('sets correct ledger_type for payment allocation', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'product_id' => test()->product->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 30000,
        'balance_amount' => 0,
        'paid' => true,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 30000,
        'status' => PaymentStatus::PAID,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 30000]);

    $allocationSummary = [
        'strategy' => 'fifo',
        'allocation_details' => [
            [
                'invoice_id' => $invoice->id,
                'allocated_amount' => 30000,
                'fully_paid' => true,
            ],
        ],
    ];

    test()->action->execute($payment, $allocationSummary);

    $creditEntry = InvoiceItemsLedger::where('payment_id', $payment->id)->first();

    expect($creditEntry->ledger_type)->toBe('payment_allocation');
});

it('handles empty allocation summary gracefully', function () {
    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 30000,
        'status' => PaymentStatus::PAID,
    ]);

    $allocationSummary = [
        'strategy' => 'fifo',
        'allocation_details' => [],
    ];

    $initialCount = InvoiceItemsLedger::count();

    test()->action->execute($payment, $allocationSummary);

    // Should not create any entries
    expect(InvoiceItemsLedger::count())->toBe($initialCount);
});
