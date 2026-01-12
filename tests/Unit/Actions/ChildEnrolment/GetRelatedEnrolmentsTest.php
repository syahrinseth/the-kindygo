<?php

use App\Actions\ChildEnrolment\GetRelatedEnrolments;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-01-23');
    // Set up tenant context for all unit tests
    $tenant = \App\Models\Tenant::factory()->create();
    $user = \App\Models\User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $parent = \App\Models\User::factory()->create([
        'current_tenant_id' => $tenant->id,
    ]);
    $centre = \App\Models\Centre::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $product = \App\Models\Product::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    // Setup Action instance
    $this->action = new GetRelatedEnrolments;

    // Attach user to tenant (without role parameter)
    $tenant->users()->attach($user->id);
    $tenant->users()->attach($parent->id);

    // Authenticate the user
    $this->actingAs($user);

    // Store references for use in tests
    $this->tenant = $tenant;
    $this->user = $user;
    $this->parent = $parent;
    $this->centre = $centre;
    $this->product = $product;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns null when child has no parent', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
    ]);

    $result = $this->action->execute($enrolment, now());

    expect($result)->toBeNull();
});

it('returns null when no enrolments need invoicing', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);
    $child->users()->attach($this->parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'date_end' => '2025-12-31',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    $result = $this->action->execute($enrolment, now());

    expect($result)->toBeNull();
});

it('returns collection of related enrolments needing invoices', function () {
    $child1 = Child::factory()->create();
    $child1->tenants()->attach($this->tenant->id);
    $child1->users()->attach($this->parent->id);

    $child2 = Child::factory()->create();
    $child2->tenants()->attach($this->tenant->id);
    $child2->users()->attach($this->parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'date_end' => null,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'date_end' => null,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    $result = $this->action->execute($enrolment1, now());

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($result->count())->toBe(2)
        ->and($result->pluck('id')->toArray())->toContain($enrolment1->id, $enrolment2->id);
});

it('excludes enrolments from different centres', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);
    $child->users()->attach($this->parent->id);

    $centre2 = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);
    ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $centre2->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    $result = $this->action->execute($enrolment1, now());

    expect($result->count())->toBe(1)
        ->and($result->first()->id)->toBe($enrolment1->id);
});

it('excludes inactive enrolments', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);
    $child->users()->attach($this->parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::PENDING,
        'date_start' => '2026-01-01',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    $result = $this->action->execute($enrolment1, now());

    expect($result->count())->toBe(1)
        ->and($result->first()->id)->toBe($enrolment1->id);
});

it('excludes enrolments that already have invoice items for next period', function () {
    $child = Child::factory()->create();
    $child->tenants()->attach($this->tenant->id);
    $child->users()->attach($this->parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
        'date_start' => '2026-01-01',
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
    ]);

    // Create invoice item for enrolment2's next period (Feb 1)
    $invoice = \App\Models\Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
    ]);

    \App\Models\InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'child_enrolment_id' => $enrolment2->id,
        'period_start' => '2026-02-01',
    ]);

    $result = $this->action->execute($enrolment1, now());

    expect($result->count())->toBe(1)
        ->and($result->first()->id)->toBe($enrolment1->id);
});
