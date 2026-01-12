<?php

use App\Actions\Invoice\CreateInvoiceItem;
use App\Enums\InvoiceItemType;
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
    Carbon::setTestNow('2026-01-23');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->action = new CreateInvoiceItem;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates invoice item with basic details', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Monthly Tuition',
    ]);

    $productPrice = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'price' => 50000,
    ]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $this->child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $periodStart = Carbon::parse('2026-01-01');
    $periodEnd = Carbon::parse('2026-01-31');

    $item = $this->action->execute(
        $invoice,
        $enrolment,
        $product,
        $periodStart,
        $periodEnd
    );

    expect($item->invoice_id)->toBe($invoice->id)
        ->and($item->product_id)->toBe($product->id)
        ->and($item->child_id)->toBe($this->child->id)
        ->and($item->child_enrolment_id)->toBe($enrolment->id)
        ->and($item->type)->toBe(InvoiceItemType::PRODUCT)
        ->and($item->name)->toBe('Monthly Tuition')
        ->and($item->price)->toBe(50000)
        ->and($item->quantity)->toBe(1)
        ->and($item->total)->toBe(50000)
        ->and($item->period_start->toDateString())->toBe('2026-01-01')
        ->and($item->period_end->toDateString())->toBe('2026-01-31');
});

it('creates invoice item with notes in description', function () {
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

    $periodStart = Carbon::parse('2026-01-15');
    $notes = 'Special discount applied';

    $item = $this->action->execute(
        $invoice,
        $enrolment,
        $product,
        $periodStart,
        null,
        $notes
    );

    expect($item->description)->toContain('Special discount applied')
        ->and($item->description)->toContain('Registration Fee');
});

it('handles single day period correctly', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'One Day Event',
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

    $periodStart = Carbon::parse('2026-01-15');

    $item = $this->action->execute(
        $invoice,
        $enrolment,
        $product,
        $periodStart,
        $periodStart
    );

    expect($item->description)->toContain('Jan 15, 2026')
        ->and($item->description)->not->toContain(' - ');
});
