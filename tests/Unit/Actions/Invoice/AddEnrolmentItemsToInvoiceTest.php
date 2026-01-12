<?php

use App\Actions\Invoice\AddEnrolmentItemsToInvoice;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-05');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->action = app(AddEnrolmentItemsToInvoice::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('adds main product items to invoice', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Tuition',
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
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
        'date_end' => null,
    ]);

    $this->action->execute($invoice, $enrolment);

    expect($invoice->invoiceItems()->count())->toBeGreaterThanOrEqual(1);
    $item = $invoice->invoiceItems()->first();
    expect($item->product_id)->toBe($product->id);
});

it('adds additional product items to invoice', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $mainProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Tuition',
    ]);

    $additionalProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Extra Class',
    ]);

    ProductPrice::factory()->create([
        'product_id' => $mainProduct->id,
        'price' => 50000,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $additionalProduct->id,
        'price' => 10000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $mainProduct->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
        'date_end' => null,
        'additional_products' => [
            [
                'product_id' => $additionalProduct->id,
                'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME->value,
                'date_start' => '2026-01-01',
                'notes' => 'Extra curriculum',
            ],
        ],
    ]);

    $this->action->execute($invoice, $enrolment);

    expect($invoice->invoiceItems()->count())->toBe(2);

    $productIds = $invoice->invoiceItems()->pluck('product_id')->toArray();
    expect($productIds)->toContain($mainProduct->id, $additionalProduct->id);
});

it('skips additional products without product_id', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $mainProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $mainProduct->id,
        'price' => 50000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $mainProduct->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
        'additional_products' => [
            [
                // Missing product_id
                'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME->value,
                'date_start' => '2026-01-01',
            ],
        ],
    ]);

    $this->action->execute($invoice, $enrolment);

    expect($invoice->invoiceItems()->count())->toBe(1);
});

it('skips additional products that do not exist', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $mainProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    ProductPrice::factory()->create([
        'product_id' => $mainProduct->id,
        'price' => 50000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $mainProduct->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
        'additional_products' => [
            [
                'product_id' => 99999, // Non-existent product
                'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME->value,
                'date_start' => '2026-01-01',
            ],
        ],
    ]);

    $this->action->execute($invoice, $enrolment);

    expect($invoice->invoiceItems()->count())->toBe(1);
});
