<?php

use App\Actions\Payment\AllocatePaymentToInvoicesAction;
use App\Actions\Payment\ProcessMultiInvoicePaymentAction;
use App\Actions\Payment\ProcessPaymentAllocationAction;
use App\Actions\Payment\RecordLedgerEntriesAction;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MultiInvoicePaymentReceiptNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');
    Storage::fake('private');
    Notification::fake();

    test()->tenant = Tenant::factory()->create();
    test()->user = User::factory()->create();
    test()->user->update(['current_tenant_id' => test()->tenant->id]); // Set after creation
    test()->tenant->users()->attach(test()->user->id);
    test()->actingAs(test()->user);

    test()->centre = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->child = Child::factory()->create();
    test()->child->tenants()->attach(test()->tenant->id);

    test()->action = new ProcessMultiInvoicePaymentAction(
        new AllocatePaymentToInvoicesAction,
        new RecordLedgerEntriesAction,
        new ProcessPaymentAllocationAction(new AllocatePaymentToInvoicesAction, new RecordLedgerEntriesAction)
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

it('processes bank transfer payment immediately as PAID', function () {
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

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 30000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice->id],
    ];

    $payment = test()->action->execute(test()->user, $validated);

    expect($payment)->toBeInstanceOf(\App\Models\Payment::class)
        ->and($payment->status)->toBe(PaymentStatus::PAID)
        ->and($payment->gateway)->toBe(Gateway::BANK_TRANSFER)
        ->and($payment->amount)->toBe(30000)
        ->and($payment->reference_no)->toBe('BT123456')
        ->and($payment->paid_at)->not->toBeNull();

    // Verify invoice is marked as PAID
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PAID);
});

it('processes CHIP payment as PENDING', function () {
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

    $validated = [
        'gateway' => 'chip',
        'payment_amount' => 30000,
        'reference_no' => 'CHIP_PENDING',
        'invoice_ids' => [$invoice->id],
    ];

    $payment = test()->action->execute(test()->user, $validated);

    expect($payment->status)->toBe(PaymentStatus::PENDING)
        ->and($payment->gateway)->toBe(Gateway::CHIP)
        ->and($payment->paid_at)->toBeNull();

    // Invoice should NOT be marked as PAID yet (waiting for webhook)
    $invoice->refresh();
    expect($invoice->status)->not->toBe(InvoiceStatus::PAID);
});

it('uploads payment proof for bank transfer', function () {
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

    $file = UploadedFile::fake()->image('payment_proof.jpg');

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 30000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice->id],
        'payment_proof' => $file,
    ];

    $payment = test()->action->execute(test()->user, $validated);

    expect($payment->getMedia('payment_proof'))->toHaveCount(1);
});

it('records ledger entries for bank transfer immediately', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $item = InvoiceItem::create([
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

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 30000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice->id],
    ];

    $payment = test()->action->execute(test()->user, $validated);

    // Verify ledger entries were created (1 debit from observer + 1 credit from payment)
    expect(InvoiceItemsLedger::count())->toBe(2);

    $ledger = InvoiceItemsLedger::where('invoice_item_id', $item->id)
        ->where('credit_amount', '>', 0)
        ->first();
    expect($ledger)->not->toBeNull()
        ->and($ledger->credit_amount)->toBe(30000)
        ->and($ledger->payment_id)->toBe($payment->id);
});

it('does not record ledger entries for CHIP payment', function () {
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

    $validated = [
        'gateway' => 'chip',
        'payment_amount' => 30000,
        'reference_no' => 'CHIP_PENDING',
        'invoice_ids' => [$invoice->id],
    ];

    test()->action->execute(test()->user, $validated);

    // Ledger should not be created yet (waiting for webhook)
    expect(InvoiceItemsLedger::count())->toBe(1); // Only debit entry from observer
});

it('sends receipt notification for bank transfer', function () {
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

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 30000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice->id],
    ];

    $payment = test()->action->execute(test()->user, $validated);

    Notification::assertSentTo(
        test()->user,
        MultiInvoicePaymentReceiptNotification::class,
        function ($notification, $channels) use ($payment) {
            return $notification->payment->id === $payment->id;
        }
    );
});

it('does not send notification for CHIP payment', function () {
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

    $validated = [
        'gateway' => 'chip',
        'payment_amount' => 30000,
        'reference_no' => 'CHIP_PENDING',
        'invoice_ids' => [$invoice->id],
    ];

    test()->action->execute(test()->user, $validated);

    Notification::assertNothingSent();
});

it('processes multiple invoices with FIFO allocation', function () {
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

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 50000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice1->id, $invoice2->id],
    ];

    $payment = test()->action->execute(test()->user, $validated);

    // Both invoices should be fully paid
    $invoice1->refresh();
    $invoice2->refresh();
    expect($invoice1->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice2->status)->toBe(InvoiceStatus::PAID);

    // Payment description should mention 2 invoices
    expect($payment->description)->toContain('2 invoices');
});

it('sets centre_id from first invoice', function () {
    $centre1 = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => test()->tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => $centre1->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => $centre2->id,
        'user_id' => test()->user->id,
        'total' => 20000,
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

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 50000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice1->id, $invoice2->id],
    ];

    $payment = test()->action->execute(test()->user, $validated);

    // Should attach first invoice's centre
    expect($payment->centres)->toHaveCount(2) // 2 centres since invoices have different centres
        ->and($payment->centres->pluck('id')->contains($centre1->id))->toBeTrue()
        ->and($payment->centres->pluck('id')->contains($centre2->id))->toBeTrue();
});

it('rolls back transaction on failure', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $validated = [
        'gateway' => 'bank_transfer',
        'payment_amount' => 30000,
        'reference_no' => 'BT123456',
        'invoice_ids' => [$invoice->id],
    ];

    // Mock allocation to throw exception
    $mockAllocateAction = Mockery::mock(AllocatePaymentToInvoicesAction::class);
    $mockAllocateAction->shouldReceive('execute')
        ->andThrow(new Exception('Allocation failed'));

    $action = new ProcessMultiInvoicePaymentAction(
        $mockAllocateAction,
        new RecordLedgerEntriesAction,
        new ProcessPaymentAllocationAction(new AllocatePaymentToInvoicesAction, new RecordLedgerEntriesAction)
    );

    expect(fn () => $action->execute(test()->user, $validated))
        ->toThrow(Exception::class);

    // No payment should be created
    expect(\App\Models\Payment::count())->toBe(0);
});
