<?php

use App\Actions\Payment\Gateways\ChipGatewayAction;
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
use App\Services\Payments\TenantChipService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'chip_brand_id' => 'brand_123',
        'chip_api_key' => 'chip-secret-key',
    ]);
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('creates CHIP payment with pending status', function () {
    $chipServiceMock = $this->mock(TenantChipService::class, function ($mock) {
        $mock->shouldReceive('createPurchase')
            ->once()
            ->andReturn((object) [
                'id' => 'chip_test_123',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/chip_test_123',
                'status' => 'pending',
            ]);
    });

    $action = app(ChipGatewayAction::class);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000, // RM 500
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

    $result = $action->execute(
        user: $this->user,
        totalAmount: $totalAmount,
        invoices: $invoices,
        userAllocation: null,
        additionalData: []
    );

    expect($result->success)->toBeTrue();
    expect($result->requiresRedirect)->toBeTrue();
    expect($result->checkoutUrl)->not->toBeNull();
    expect($result->payment)->toBeInstanceOf(Payment::class);
    expect($result->payment->status)->toBe(PaymentStatus::PENDING);
    expect($result->payment->gateway)->toBe(Gateway::CHIP);
    expect($result->payment->amount)->toBe($totalAmount);
    expect($result->payment->tenant_id)->toBe($this->tenant->id);
    expect($result->payment->user_id)->toBe($this->user->id);

    // Verify invoice is attached
    expect($result->payment->invoices()->withoutGlobalScope(TenantScope::class)->count())->toBe(1);

    // Verify centre allocation is attached
    expect($result->payment->centres()->withoutGlobalScope(TenantScope::class)->count())->toBeGreaterThan(0);
});

it('creates CHIP payment with user-defined allocation', function () {
    $chipServiceMock = $this->mock(TenantChipService::class, function ($mock) {
        $mock->shouldReceive('createPurchase')
            ->once()
            ->andReturn((object) [
                'id' => 'chip_test_456',
                'checkout_url' => 'https://gate.chip-in.asia/checkout/chip_test_456',
                'status' => 'pending',
            ]);
    });

    $action = app(ChipGatewayAction::class);

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

    $invoices = [['id' => $invoice1->id], ['id' => $invoice2->id]];
    $userAllocation = [
        $invoice1->id => 15000, // Partial payment
        $invoice2->id => 10000, // Partial payment
    ];
    $totalAmount = 25000;

    $result = $action->execute(
        user: $this->user,
        totalAmount: $totalAmount,
        invoices: $invoices,
        userAllocation: $userAllocation,
        additionalData: []
    );

    expect($result->success)->toBeTrue();
    expect($result->payment->amount)->toBe($totalAmount);

    // Verify both invoices are attached
    expect($result->payment->invoices()->withoutGlobalScope(TenantScope::class)->count())->toBe(2);

    // Verify user allocation is stored in gateway_payment_data
    expect($result->payment->gateway_payment_data['user_allocation'])->toBe($userAllocation);
});

it('handles CHIP service failure gracefully', function () {
    $chipServiceMock = $this->mock(TenantChipService::class, function ($mock) {
        $mock->shouldReceive('createPurchase')
            ->once()
            ->andThrow(new Exception('CHIP API error'));
    });

    $action = app(ChipGatewayAction::class);

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

    $invoices = [['id' => $invoice->id]];

    expect(fn () => $action->execute(
        user: $this->user,
        totalAmount: 50000,
        invoices: $invoices,
        userAllocation: null,
        additionalData: []
    ))->toThrow(Exception::class);

    // Note: Payment record will be created but may not be found if transaction rolls back
});

it('throws exception when user has no current tenant', function () {
    $action = app(ChipGatewayAction::class);

    $userWithoutTenant = User::factory()->create(['current_tenant_id' => null]);
    $this->actingAs($userWithoutTenant);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoices = [['id' => $invoice->id]];

    expect(fn () => $action->execute(
        user: $userWithoutTenant,
        totalAmount: 50000,
        invoices: $invoices,
        userAllocation: null,
        additionalData: []
    ))->toThrow(RuntimeException::class, 'User does not have a current tenant');
});

it('does not create a payment when CHIP is not configured for the tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user->id);

    expect(fn () => app(ChipGatewayAction::class)->execute(
        user: $user,
        totalAmount: 50000,
        invoices: [],
    ))->toThrow(RuntimeException::class, 'CHIP payments are not configured for this organisation.');

    expect(Payment::withoutGlobalScopes()->count())->toBe(0);
});

it('returns requiresRedirect as true', function () {
    $action = app(ChipGatewayAction::class);
    expect($action->requiresRedirect())->toBeTrue();
});

it('returns supportsWebhook as true', function () {
    $action = app(ChipGatewayAction::class);
    expect($action->supportsWebhook())->toBeTrue();
});
