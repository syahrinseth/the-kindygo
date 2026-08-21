<?php

use App\Actions\Invoice\CreateInvoiceForGroup;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-23');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->parent = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->tenant->users()->attach($this->parent->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->action = app(CreateInvoiceForGroup::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates invoice for single enrolment', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'price' => 50000]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
    ]);

    $group = [
        'parent' => $this->parent,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'enrolments' => collect([$enrolment]),
    ];

    $invoice = $this->action->execute($group);

    expect($invoice)->not->toBeNull()
        ->and($invoice->tenant_id)->toBe($this->tenant->id)
        ->and($invoice->centre_id)->toBe($this->centre->id)
        ->and($invoice->user_id)->toBe($this->parent->id)
        ->and($invoice->status->value)->toBe(InvoiceStatus::PENDING->value)
        ->and($invoice->invoiceItems()->count())->toBeGreaterThanOrEqual(1);
});

it('creates invoice for multiple enrolments', function () {
    $child1 = Child::factory()->create();
    $child1->tenants()->attach($this->tenant->id);

    $child2 = Child::factory()->create();
    $child2->tenants()->attach($this->tenant->id);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'price' => 50000]);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-15',
    ]);

    $group = [
        'parent' => $this->parent,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'enrolments' => collect([$enrolment1, $enrolment2]),
    ];

    $invoice = $this->action->execute($group);

    expect($invoice->invoiceItems()->count())->toBeGreaterThanOrEqual(2);
});

it('sets invoice date to earliest enrolment start date', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'price' => 50000]);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-02-01',
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-15',
    ]);

    $group = [
        'parent' => $this->parent,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'enrolments' => collect([$enrolment1, $enrolment2]),
    ];

    $invoice = $this->action->execute($group);

    expect($invoice->date->toDateString())->toBe('2026-01-15');
});

it('sets due date to 7 days after invoice date', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'price' => 50000]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-10',
    ]);

    $group = [
        'parent' => $this->parent,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'enrolments' => collect([$enrolment]),
    ];

    $invoice = $this->action->execute($group);

    expect($invoice->due_at->toDateString())->toBe('2026-01-17');
});

it('updates invoice totals after creation', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductPrice::factory()->create(['product_id' => $product->id, 'price' => 50000]);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $product->id,
        'tenant_id' => $this->tenant->id,
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
        'date_start' => '2026-01-01',
    ]);

    $group = [
        'parent' => $this->parent,
        'centre_id' => $this->centre->id,
        'tenant_id' => $this->tenant->id,
        'enrolments' => collect([$enrolment]),
    ];

    $invoice = $this->action->execute($group);

    expect($invoice->total_items)->toBeGreaterThan(0)
        ->and($invoice->total_amount)->toBeGreaterThan(0)
        ->and($invoice->total_amount)->toBeGreaterThan(0);
});
