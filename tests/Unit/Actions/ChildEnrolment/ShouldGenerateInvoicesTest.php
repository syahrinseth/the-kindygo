<?php

use App\Actions\ChildEnrolment\GetNextBillingDate;
use App\Actions\ChildEnrolment\GetNextBillingPeriodStart;
use App\Actions\ChildEnrolment\ShouldGenerateInvoices;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-15 10:00:00');
    $getNextBillingDate = new GetNextBillingDate;
    $getNextBillingPeriodStart = new GetNextBillingPeriodStart($getNextBillingDate);
    $this->action = new ShouldGenerateInvoices($getNextBillingPeriodStart);

    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->parent = User::factory()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns false when child has no parent', function () {
    $child = Child::factory()->create();
    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-01',
        'date_end' => null,
    ])->load('child');

    $result = $this->action->execute($enrolment, 30);

    expect($result)->toBeFalse();
});

it('returns false when enrolment end date has passed', function () {
    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-10',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ])->load('child');

    $result = $this->action->execute($enrolment, 30);

    expect($result)->toBeFalse();
});

it('returns false when billing date is beyond days ahead threshold', function () {
    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-01',
        'date_end' => null,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ])->load('child');

    // Only 5 days ahead, next bill is Feb 1
    $result = $this->action->execute($enrolment, 5);

    expect($result)->toBeFalse();
});

it('returns true when all conditions are met', function () {
    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-01',
        'date_end' => null,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ])->load('child');

    $result = $this->action->execute($enrolment, 30);

    expect($result)->toBeTrue();
});

it('returns true when billing date is within days ahead threshold', function () {
    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-06',
        'date_end' => null,
        'billed_every' => ChildEnrolmentBilledEvery::WEEKLY,
    ])->load('child');

    // Next billing is Jan 20
    $result = $this->action->execute($enrolment, 10);

    expect($result)->toBeTrue();
});

it('returns false when existing invoice item exists for period', function () {
    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-01',
        'date_end' => null,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ])->load('child');

    // Create invoice item for Feb 1
    $invoice = \App\Models\Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    \App\Models\InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'child_enrolment_id' => $enrolment->id,
        'period_start' => '2026-02-01',
    ]);

    $result = $this->action->execute($enrolment, 30);

    expect($result)->toBeFalse();
});

it('returns false when next billing period start is null for one time billing with existing invoice', function () {
    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'date_start' => '2026-01-01',
        'date_end' => '2026-01-05',
        'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME,
    ])->load('child');

    // Create invoice item for one-time billing
    $invoice = \App\Models\Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    \App\Models\InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'child_enrolment_id' => $enrolment->id,
        'period_start' => '2026-01-01',
    ]);

    $result = $this->action->execute($enrolment, 30);

    expect($result)->toBeFalse();
});
