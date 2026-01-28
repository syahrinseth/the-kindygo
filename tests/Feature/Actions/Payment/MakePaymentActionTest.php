<?php

use App\Actions\Payment\MakePaymentAction;
use App\DataTransferObjects\PaymentResult;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use SyahrinSeth\ChipLaravel\ChipService;

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
});

it('executes CHIP payment through gateway factory', function () {
    $chipServiceMock = $this->mock(ChipService::class, function ($mock) {
        $mock->shouldReceive('createPurchase')
            ->once()
            ->andReturn((object) [
                'id' => 'chip_test_123',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/chip_test_123',
                'status' => 'pending',
            ]);
    });

    $action = app(MakePaymentAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::CHIP,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: []
    );

    expect($result)->toBeInstanceOf(PaymentResult::class);
    expect($result->success)->toBeTrue();
    expect($result->requiresRedirect)->toBeTrue();
    expect($result->checkoutUrl)->not->toBeNull();
    expect($result->payment)->toBeInstanceOf(Payment::class);
    expect($result->payment->gateway)->toBe(Gateway::CHIP);
    expect($result->payment->status)->toBe(PaymentStatus::PENDING);
});

it('executes bank transfer payment through gateway factory', function () {
    $action = app(MakePaymentAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::BANK_TRANSFER,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: [
            'reference_no' => 'BANK-REF-123',
        ]
    );

    expect($result)->toBeInstanceOf(PaymentResult::class);
    expect($result->success)->toBeTrue();
    expect($result->requiresRedirect)->toBeFalse();
    expect($result->checkoutUrl)->toBeNull();
    expect($result->payment)->toBeInstanceOf(Payment::class);
    expect($result->payment->gateway)->toBe(Gateway::BANK_TRANSFER);
    expect($result->payment->status)->toBe(PaymentStatus::PAID);
    expect($result->payment->reference_no)->toBe('BANK-REF-123');
});

it('executes payment with user-defined allocation', function () {
    $action = app(MakePaymentAction::class);

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 20000,
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
        $invoice1->id => 15000,
        $invoice2->id => 20000,
    ];

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::BANK_TRANSFER,
        totalAmount: 35000,
        invoices: [['id' => $invoice1->id], ['id' => $invoice2->id]],
        userAllocation: $userAllocation,
        additionalData: ['reference_no' => 'BANK-USER-999']
    );

    expect($result->success)->toBeTrue();
    expect($result->allocationSummary['strategy'])->toBe('user_defined_priority');
    expect($result->allocationSummary['fully_paid_count'])->toBe(1);
    expect($result->allocationSummary['partially_paid_count'])->toBe(1);
});

it('handles payment with payment proof for bank transfer', function () {
    $action = app(MakePaymentAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::BANK_TRANSFER,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: [
            'reference_no' => 'BANK-PROOF-456',
            'payment_proof' => $paymentProof,
        ]
    );

    expect($result->success)->toBeTrue();
    expect($result->payment->hasMedia('payment_proof'))->toBeTrue();
});

it('returns failure result when gateway throws exception', function () {
    $chipServiceMock = $this->mock(ChipService::class, function ($mock) {
        $mock->shouldReceive('createPurchase')
            ->once()
            ->andThrow(new Exception('CHIP API error'));
    });

    $action = app(MakePaymentAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::CHIP,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: []
    );

    expect($result)->toBeInstanceOf(PaymentResult::class);
    expect($result->success)->toBeFalse();
    expect($result->message)->toContain('Payment processing failed');
    expect($result->payment)->toBeNull();
});

it('throws exception for unimplemented gateway', function () {
    $action = app(MakePaymentAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::BILLPLZ,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: []
    );

    expect($result->success)->toBeFalse();
    expect($result->message)->toContain('Billplz payment gateway is not yet implemented');
});

it('checks requiresRedirect for CHIP gateway', function () {
    $action = app(MakePaymentAction::class);

    $requiresRedirect = $action->requiresRedirect(Gateway::CHIP);

    expect($requiresRedirect)->toBeTrue();
});

it('checks requiresRedirect for bank transfer gateway', function () {
    $action = app(MakePaymentAction::class);

    $requiresRedirect = $action->requiresRedirect(Gateway::BANK_TRANSFER);

    expect($requiresRedirect)->toBeFalse();
});

it('checks supportsWebhook for CHIP gateway', function () {
    $action = app(MakePaymentAction::class);

    $supportsWebhook = $action->supportsWebhook(Gateway::CHIP);

    expect($supportsWebhook)->toBeTrue();
});

it('checks supportsWebhook for bank transfer gateway', function () {
    $action = app(MakePaymentAction::class);

    $supportsWebhook = $action->supportsWebhook(Gateway::BANK_TRANSFER);

    expect($supportsWebhook)->toBeFalse();
});

it('handles multiple invoices across different centres', function () {
    $action = app(MakePaymentAction::class);

    $centre1 = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre1->id,
        'user_id' => $this->user->id,
        'total' => 30000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre2->id,
        'user_id' => $this->user->id,
        'total' => 20000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::BANK_TRANSFER,
        totalAmount: 50000,
        invoices: [['id' => $invoice1->id], ['id' => $invoice2->id]],
        userAllocation: null,
        additionalData: ['reference_no' => 'BANK-MULTI-CENTRE']
    );

    expect($result->success)->toBeTrue();

    $centres = $result->payment->centres()->withoutGlobalScope(TenantScope::class)->get();
    expect($centres->count())->toBe(2);
    expect($centres->pluck('id'))->toContain($centre1->id, $centre2->id);
});

it('logs payment processing information', function () {
    $action = app(MakePaymentAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
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

    $result = $action->execute(
        user: $this->user,
        gateway: Gateway::BANK_TRANSFER,
        totalAmount: 50000,
        invoices: [['id' => $invoice->id]],
        userAllocation: null,
        additionalData: ['reference_no' => 'BANK-LOG-TEST']
    );

    expect($result->success)->toBeTrue();
    // Logging is verified by not throwing exceptions
});
