<?php

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ChildEnrolment;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\Centre;
use App\Models\User;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Actions\Invoice\AddProductItemsToInvoice;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-23');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->action = app(AddProductItemsToInvoice::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates one-time invoice item', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Registration Fee',
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'price' => 10000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->action->execute(
        $invoice,
        $enrolment,
        $product,
        ChildEnrolmentBilledEvery::ONE_TIME,
        Carbon::parse('2026-01-01'),
        null
    );

    expect($invoice->invoiceItems()->count())->toBe(1);
    $item = $invoice->invoiceItems()->first();
    expect($item->name)->toBe('Registration Fee')
        ->and($item->price)->toBe(10000);
});

it('creates recurring invoice items', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'date' => Carbon::parse('2026-01-05'),
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Monthly Tuition',
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'price' => 50000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->action->execute(
        $invoice,
        $enrolment,
        $product,
        ChildEnrolmentBilledEvery::MONTHLY,
        Carbon::parse('2026-01-01'),
        null
    );

    expect($invoice->invoiceItems()->count())->toBeGreaterThanOrEqual(1);
});

it('passes notes to invoice items', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Special Service',
    ]);

    ProductPrice::factory()->create([
        'product_id' => $product->id,
        'price' => 5000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $this->action->execute(
        $invoice,
        $enrolment,
        $product,
        ChildEnrolmentBilledEvery::ONE_TIME,
        Carbon::parse('2026-01-01'),
        null,
        'Custom note for this item'
    );

    $item = $invoice->invoiceItems()->first();
    expect($item->description)->toContain('Custom note for this item');
});
