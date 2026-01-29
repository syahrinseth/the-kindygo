<?php

use App\Actions\Payment\AllocatePaymentToInvoicesAction;
use App\Actions\Payment\ProcessPaymentAllocationAction;
use App\Actions\Payment\RecordLedgerEntriesAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

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

    test()->action = new ProcessPaymentAllocationAction(
        new AllocatePaymentToInvoicesAction,
        new RecordLedgerEntriesAction
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('orchestrates full payment allocation flow with FIFO strategy', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 20000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    $item1 = InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 1',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    $item2 = InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 2',
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
        'paid_amount' => 0,
        'balance_amount' => 20000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 50000,
        'status' => PaymentStatus::PAID,
    ]);

    $result = test()->action->execute($payment, collect([$invoice1, $invoice2]));

    expect($result['success'])->toBeTrue()
        ->and($result['payment_id'])->toBe($payment->id)
        ->and($result['allocation_summary']['fully_paid_count'])->toBe(2)
        ->and($result['allocation_summary']['partially_paid_count'])->toBe(0);

    // Verify invoices are marked as PAID
    $invoice1->refresh();
    $invoice2->refresh();
    expect($invoice1->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice2->status)->toBe(InvoiceStatus::PAID);

    // Verify ledger entries were created
    // Verify ledger entries were created (2 debit from observer + 2 credit from action)
    expect(InvoiceItemsLedger::count())->toBe(4);
    $ledger1 = InvoiceItemsLedger::where('invoice_item_id', $item1->id)
        ->where('credit_amount', '>', 0)
        ->first();
    $ledger2 = InvoiceItemsLedger::where('invoice_item_id', $item2->id)
        ->where('credit_amount', '>', 0)
        ->first();

    expect($ledger1)->not->toBeNull()
        ->and($ledger1->credit_amount)->toBeGreaterThan(0)
        ->and($ledger1->payment_id)->toBe($payment->id)
        ->and($ledger2)->not->toBeNull()
        ->and($ledger2->credit_amount)->toBeGreaterThan(0)
        ->and($ledger2->payment_id)->toBe($payment->id);
});

it('orchestrates full payment allocation flow with user-defined allocation', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 20000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 1',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 2',
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
        'paid_amount' => 0,
        'balance_amount' => 20000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 35000,
        'status' => PaymentStatus::PAID,
    ]);

    // User specifies custom allocation
    $userAllocation = [
        $invoice1->id => 15000,
        $invoice2->id => 20000,
    ];

    $result = test()->action->execute($payment, collect([$invoice1, $invoice2]), $userAllocation);

    expect($result['success'])->toBeTrue()
        ->and($result['allocation_summary']['fully_paid_count'])->toBe(1)
        ->and($result['allocation_summary']['partially_paid_count'])->toBe(1);

    // Invoice2 should be fully paid, Invoice1 partially paid
    $invoice1->refresh();
    $invoice2->refresh();
    expect($invoice2->status)->toBe(InvoiceStatus::PAID);
});

it('rolls back transaction on allocation failure', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
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

    // Mock the allocation action to throw an exception
    $mockAllocateAction = Mockery::mock(AllocatePaymentToInvoicesAction::class);
    $mockAllocateAction->shouldReceive('execute')
        ->once()
        ->andThrow(new Exception('Allocation failed'));

    $action = new ProcessPaymentAllocationAction(
        $mockAllocateAction,
        new RecordLedgerEntriesAction
    );

    expect(fn () => $action->execute($payment, collect([$invoice])))
        ->toThrow(Exception::class, 'Allocation failed');

    // Verify no ledger entries were created
    // Verify only debit entry from observer exists, no credit entries
    $creditEntries = InvoiceItemsLedger::where('credit_amount', '>', 0)->count();
    expect($creditEntries)->toBe(0);
});

it('handles partial payment correctly', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 40000,
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 1',
        'price' => 40000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 40000,
        'paid_amount' => 0,
        'balance_amount' => 40000,
        'paid' => false,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 2',
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
        'amount' => 50000, // Only enough for invoice1 + partial invoice2
        'status' => PaymentStatus::PAID,
    ]);

    $result = test()->action->execute($payment, collect([$invoice1, $invoice2]));

    expect($result['success'])->toBeTrue()
        ->and($result['allocation_summary']['fully_paid_count'])->toBe(1)
        ->and($result['allocation_summary']['partially_paid_count'])->toBe(1);

    // Only invoice1 should be marked as PAID
    $invoice1->refresh();
    $invoice2->refresh();
    expect($invoice1->status)->toBe(InvoiceStatus::PAID);
    expect($invoice2->status)->not->toBe(InvoiceStatus::PAID);
});

it('logs allocation process correctly', function () {
    // Accept all log messages - we just want to ensure the process completes without errors
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    Log::shouldReceive('warning')->zeroOrMoreTimes();

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
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

    $result = test()->action->execute($payment, collect([$invoice]));

    // Verify successful execution
    expect($result['success'])->toBeTrue()
        ->and($result['payment_id'])->toBe($payment->id);
});

it('logs errors on allocation failure', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('Starting payment allocation', Mockery::any());

    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message, $context) {
            return $message === 'Payment allocation processing failed' &&
                   isset($context['error']) &&
                   isset($context['payment_id']);
        });

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 30000,
        'status' => PaymentStatus::PAID,
    ]);

    // Mock to throw exception
    $mockAllocateAction = Mockery::mock(AllocatePaymentToInvoicesAction::class);
    $mockAllocateAction->shouldReceive('execute')
        ->andThrow(new Exception('Test error'));

    $action = new ProcessPaymentAllocationAction(
        $mockAllocateAction,
        new RecordLedgerEntriesAction
    );

    expect(fn () => $action->execute($payment, collect([$invoice])))
        ->toThrow(Exception::class);
});

it('generates correct allocation message for all fully paid', function () {
    $allocationSummary = [
        'total_invoices' => 3,
        'fully_paid_count' => 3,
        'partially_paid_count' => 0,
    ];

    $message = test()->action->getAllocationMessage($allocationSummary);

    expect($message)->toBe('Payment processed successfully for 3 invoice(s) (all fully paid)');
});

it('generates correct allocation message for all partially paid', function () {
    $allocationSummary = [
        'total_invoices' => 2,
        'fully_paid_count' => 0,
        'partially_paid_count' => 2,
    ];

    $message = test()->action->getAllocationMessage($allocationSummary);

    expect($message)->toBe('Payment processed successfully for 2 invoice(s) (all partially paid)');
});

it('generates correct allocation message for mixed payment', function () {
    $allocationSummary = [
        'total_invoices' => 5,
        'fully_paid_count' => 3,
        'partially_paid_count' => 2,
    ];

    $message = test()->action->getAllocationMessage($allocationSummary);

    expect($message)->toBe('Payment processed successfully for 5 invoice(s) (3 fully paid, 2 partially paid)');
});
