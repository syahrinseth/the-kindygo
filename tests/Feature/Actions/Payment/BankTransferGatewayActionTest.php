<?php

use App\Actions\Payment\Gateways\BankTransferGatewayAction;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MultiInvoicePaymentReceiptNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->action = app(BankTransferGatewayAction::class);
});

it('creates bank transfer payment with PAID status', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 50000, // RM 500
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Test Item',
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
        'paid_amount' => 0,
        'balance_amount' => 50000,
    ]);

    $invoices = [['id' => $invoice->id]];
    $totalAmount = 50000;
    $additionalData = [
        'reference_no' => 'BANK-REF-123',
    ];

    $result = $this->action->execute(
        user: $this->user,
        totalAmount: $totalAmount,
        invoices: $invoices,
        userAllocation: null,
        additionalData: $additionalData
    );

    expect($result->success)->toBeTrue();
    expect($result->requiresRedirect)->toBeFalse();
    expect($result->checkoutUrl)->toBeNull();
    expect($result->payment)->toBeInstanceOf(Payment::class);
    expect($result->payment->status)->toBe(PaymentStatus::PAID);
    expect($result->payment->gateway)->toBe(Gateway::BANK_TRANSFER);
    expect($result->payment->amount)->toBe($totalAmount);
    expect($result->payment->reference_no)->toBe('BANK-REF-123');
    expect($result->payment->paid_at)->not->toBeNull();
    expect($result->payment->tenant_id)->toBe($this->tenant->id);
    expect($result->payment->user_id)->toBe($this->user->id);

    // Verify invoice is paid
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PAID);

    // Verify invoice item is paid
    $invoiceItem = $invoice->invoiceItems()->first();
    expect($invoiceItem->paid)->toBeTrue();
    expect($invoiceItem->paid_amount)->toBe(50000);
    expect($invoiceItem->balance_amount)->toBe(0);

    // Verify ledger entries were recorded
    $ledgerCount = InvoiceItemsLedger::where('payment_id', $result->payment->id)->count();
    expect($ledgerCount)->toBeGreaterThan(0);

    // Verify notification was sent
    Notification::assertSentTo($this->user, MultiInvoicePaymentReceiptNotification::class);
});

it('handles payment proof upload', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 50000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Test Item',
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
        'paid_amount' => 0,
        'balance_amount' => 50000,
    ]);

    $paymentProof = UploadedFile::fake()->image('receipt.jpg');

    $result = $this->action->execute(
        user: $this->user,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: [
            'reference_no' => 'BANK-REF-456',
            'payment_proof' => $paymentProof,
        ]
    );

    expect($result->success)->toBeTrue();
    expect($result->payment->hasMedia('payment_proof'))->toBeTrue();
});

it('allocates payment using FIFO when no user allocation provided', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 30000,
        'status' => InvoiceStatus::PENDING,
        'due_at' => now()->subDays(10), // Older invoice
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 20000,
        'status' => InvoiceStatus::PENDING,
        'due_at' => now()->subDays(5), // Newer invoice
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Item 1',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Item 2',
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
        'paid_amount' => 0,
        'balance_amount' => 20000,
    ]);

    // Pay only enough to cover first invoice fully
    $result = $this->action->execute(
        user: $this->user,
        totalAmount: 30000,
        invoices: [['id' => $invoice1->id], ['id' => $invoice2->id]],
        userAllocation: null,
        additionalData: ['reference_no' => 'BANK-FIFO-789']
    );

    expect($result->success)->toBeTrue();
    expect($result->allocationSummary['fully_paid_count'])->toBe(1);
    expect($result->allocationSummary['partially_paid_count'])->toBe(0);
    expect($result->allocationSummary['strategy'])->toBe('fifo_priority');

    // First invoice should be fully paid (older)
    $invoice1->refresh();
    expect($invoice1->status)->toBe(InvoiceStatus::PAID);

    // Second invoice should still be pending or overdue
    $invoice2->refresh();
    expect($invoice2->status)->toBeIn([InvoiceStatus::PENDING, InvoiceStatus::OVERDUE]);
});

it('allocates payment using user-defined allocation', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 20000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Item 1',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Item 2',
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
        'paid_amount' => 0,
        'balance_amount' => 20000,
    ]);

    $userAllocation = [
        $invoice1->id => 15000, // Partial payment
        $invoice2->id => 20000, // Full payment
    ];

    $result = $this->action->execute(
        user: $this->user,
        totalAmount: 35000,
        invoices: [['id' => $invoice1->id], ['id' => $invoice2->id]],
        userAllocation: $userAllocation,
        additionalData: ['reference_no' => 'BANK-USER-999']
    );

    expect($result->success)->toBeTrue();
    expect($result->allocationSummary['fully_paid_count'])->toBe(1);
    expect($result->allocationSummary['partially_paid_count'])->toBe(1);
    expect($result->allocationSummary['strategy'])->toBe('user_defined_priority');

    // First invoice should be partially paid
    $invoice1->refresh();
    expect($invoice1->status)->toBe(InvoiceStatus::PARTIALLY_PAID);

    // Second invoice should be fully paid
    $invoice2->refresh();
    expect($invoice2->status)->toBe(InvoiceStatus::PAID);
});

it('attaches centre allocations correctly', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 50000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'name' => 'Test Item',
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
        'paid_amount' => 0,
        'balance_amount' => 50000,
    ]);

    $result = $this->action->execute(
        user: $this->user,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: ['reference_no' => 'BANK-CENTRE-001']
    );

    expect($result->success)->toBeTrue();

    $centres = $result->payment->centres()->withoutGlobalScope(TenantScope::class)->get();
    expect($centres->count())->toBe(1);
    expect($centres->first()->id)->toBe($this->centre->id);
    expect($centres->first()->pivot->allocated_amount)->toBe(50000);
});

it('throws exception when user has no current tenant', function () {
    $userWithoutTenant = User::factory()->create(['current_tenant_id' => null]);
    $this->actingAs($userWithoutTenant);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 50000,
        'status' => InvoiceStatus::PENDING,
    ]);

    expect(fn () => $this->action->execute(
        user: $userWithoutTenant,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: ['reference_no' => 'BANK-NO-TENANT']
    ))->toThrow(RuntimeException::class, 'User does not have a current tenant');
});

it('returns requiresRedirect as false', function () {
    expect($this->action->requiresRedirect())->toBeFalse();
});

it('returns supportsWebhook as false', function () {
    expect($this->action->supportsWebhook())->toBeFalse();
});
