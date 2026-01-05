<?php

use App\Actions\ChildEnrolment\ActivateEnrolments;
use App\Enums\ChildEnrolmentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->action = new ActivateEnrolments;

    // Create tenant and related data needed for factories
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('activates enrolment with draft status', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::DRAFT,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $this->action->execute(collect([$enrolment]));

    expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE);
});

it('activates enrolment with pending status', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::PENDING,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $this->action->execute(collect([$enrolment]));

    expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE);
});

it('activates enrolment with inactive status', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::INACTIVE,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $this->action->execute(collect([$enrolment]));

    expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE);
});

it('does not change already active enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::ACTIVE,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $this->action->execute(collect([$enrolment]));

    expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE);
});

it('does not change completed enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::COMPLETED,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $this->action->execute(collect([$enrolment]));

    expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::COMPLETED);
});

it('does not change cancelled enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::CANCELLED,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $this->action->execute(collect([$enrolment]));

    expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::CANCELLED);
});

it('activates multiple enrolments', function () {
    $enrolments = collect();

    for ($i = 0; $i < 3; $i++) {
        $enrolments->push(
            ChildEnrolment::factory()->create([
                'status' => ChildEnrolmentStatus::DRAFT,
                'centre_id' => $this->centre->id,
                'child_id' => $this->child->id,
                'product_id' => $this->product->id,
            ])
        );
    }

    $this->action->execute($enrolments);

    $enrolments->each(fn ($enrolment) => expect($enrolment->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE)
    );
});

it('handles mixed statuses correctly', function () {
    $draft = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::DRAFT,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $active = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::ACTIVE,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $completed = ChildEnrolment::factory()->create([
        'status' => ChildEnrolmentStatus::COMPLETED,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
    ]);

    $enrolments = collect([$draft, $active, $completed]);

    $this->action->execute($enrolments);

    expect($draft->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE)
        ->and($active->fresh()->status)->toBe(ChildEnrolmentStatus::ACTIVE)
        ->and($completed->fresh()->status)->toBe(ChildEnrolmentStatus::COMPLETED);
});
