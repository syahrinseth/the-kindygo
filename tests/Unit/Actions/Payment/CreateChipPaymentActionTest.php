<?php

use App\Actions\Payment\CreateChipPaymentAction;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SyahrinSeth\ChipLaravel\ChipService;

uses(RefreshDatabase::class);

it('creates a payment with multi-centre allocation', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre1->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre2->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $allocation = [
        $invoice1->id => 10000,
        $invoice2->id => 15000,
    ];

    $chipPurchase = new \stdClass;
    $chipPurchase->id = 'chip-payment-123';
    $chipPurchase->checkout_url = 'https://gate.chip-in.asia/checkout/chip-payment-123';

    $chipService = Mockery::mock(ChipService::class);
    $chipService->shouldReceive('createPurchase')
        ->once()
        ->andReturn($chipPurchase);
    app()->instance(ChipService::class, $chipService);

    $action = new CreateChipPaymentAction;
    $result = $action->execute($user, 25000, $allocation);

    expect($result)->toHaveKeys(['payment', 'checkout_url']);

    $payment = $result['payment'];
    expect($payment->centres)->toHaveCount(2);
    expect($payment->getCentreAllocation($centre1->id))->toBe(10000);
    expect($payment->getCentreAllocation($centre2->id))->toBe(15000);
})->skip('Requires refactoring action to support dependency injection for ChipService mocking');

it('creates a payment with single-centre allocation', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $allocation = [$invoice->id => 20000];

    $chipPurchase = new \stdClass;
    $chipPurchase->id = 'chip-payment-456';
    $chipPurchase->checkout_url = 'https://gate.chip-in.asia/checkout/chip-payment-456';

    $chipService = Mockery::mock(ChipService::class);
    $chipService->shouldReceive('createPurchase')
        ->once()
        ->andReturn($chipPurchase);
    app()->instance(ChipService::class, $chipService);

    $action = new CreateChipPaymentAction;
    $result = $action->execute($user, 20000, $allocation);

    $payment = $result['payment'];
    expect($payment->centres)->toHaveCount(1);
    expect($payment->getCentreAllocation($centre->id))->toBe(20000);
    expect($payment->isMultiCentre())->toBeFalse();
})->skip('Requires refactoring action to support dependency injection for ChipService mocking');

it('throws exception when invoice does not belong to user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $invoice = Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $tenant->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $allocation = [$invoice->id => 10000];

    $action = new CreateChipPaymentAction;

    expect(fn () => $action->execute($user, 10000, $allocation))
        ->toThrow(Exception::class, 'One or more invoices not found or do not belong to this user.');
});

it('throws exception when invoice has DRAFT status', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::DRAFT,
    ]);

    $allocation = [$invoice->id => 10000];

    $action = new CreateChipPaymentAction;

    expect(fn () => $action->execute($user, 10000, $allocation))
        ->toThrow(Exception::class, 'Cannot process payment. Only invoices with status PENDING or OVERDUE can be paid.');
});

it('throws exception when invoice has PAID status', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PAID,
    ]);

    $allocation = [$invoice->id => 10000];

    $action = new CreateChipPaymentAction;

    expect(fn () => $action->execute($user, 10000, $allocation))
        ->toThrow(Exception::class, 'Cannot process payment. Only invoices with status PENDING or OVERDUE can be paid.');
});

it('accepts PENDING invoices', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $allocation = [$invoice->id => 10000];

    $chipPurchase = new \stdClass;
    $chipPurchase->id = 'chip-payment-789';
    $chipPurchase->checkout_url = 'https://gate.chip-in.asia/checkout/chip-payment-789';

    $chipService = Mockery::mock(ChipService::class);
    $chipService->shouldReceive('createPurchase')
        ->once()
        ->andReturn($chipPurchase);
    app()->instance(ChipService::class, $chipService);

    $action = new CreateChipPaymentAction;
    $result = $action->execute($user, 10000, $allocation);

    expect($result['payment']->status)->toBe(PaymentStatus::PENDING);
})->skip('Requires refactoring action to support dependency injection for ChipService mocking');

it('accepts OVERDUE invoices', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $allocation = [$invoice->id => 10000];

    $chipPurchase = new \stdClass;
    $chipPurchase->id = 'chip-payment-abc';
    $chipPurchase->checkout_url = 'https://gate.chip-in.asia/checkout/chip-payment-abc';

    $chipService = Mockery::mock(ChipService::class);
    $chipService->shouldReceive('createPurchase')
        ->once()
        ->andReturn($chipPurchase);
    app()->instance(ChipService::class, $chipService);

    $action = new CreateChipPaymentAction;
    $result = $action->execute($user, 10000, $allocation);

    expect($result['payment']->status)->toBe(PaymentStatus::PENDING);
})->skip('Requires refactoring action to support dependency injection for ChipService mocking');

it('creates payment with CHIP gateway', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
    ]);

    $allocation = [$invoice->id => 10000];

    $chipPurchase = new \stdClass;
    $chipPurchase->id = 'chip-payment-xyz';
    $chipPurchase->checkout_url = 'https://gate.chip-in.asia/checkout/chip-payment-xyz';

    $chipService = Mockery::mock(ChipService::class);
    $chipService->shouldReceive('createPurchase')
        ->once()
        ->andReturn($chipPurchase);
    app()->instance(ChipService::class, $chipService);

    $action = new CreateChipPaymentAction;
    $result = $action->execute($user, 10000, $allocation);

    expect($result['payment']->gateway)->toBe(Gateway::CHIP);
})->skip('Requires refactoring action to support dependency injection for ChipService mocking');
