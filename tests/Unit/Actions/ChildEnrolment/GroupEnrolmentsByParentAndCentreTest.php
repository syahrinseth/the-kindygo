<?php

use App\Actions\ChildEnrolment\GroupEnrolmentsByParentAndCentre;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->action = new GroupEnrolmentsByParentAndCentre;

    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('groups enrolments by parent and centre', function () {
    $parent = User::factory()->create();
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent->id);
    $child2->users()->attach($parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    // Load the child relationship
    $enrolments = collect([$enrolment1->load('child'), $enrolment2->load('child')]);
    $result = $this->action->execute($enrolments);

    expect($result)->toHaveCount(1)
        ->and($result)->toHaveKey($this->tenant->id.'_'.$parent->id.'_'.$this->centre->id)
        ->and($result[$this->tenant->id.'_'.$parent->id.'_'.$this->centre->id]['enrolments'])->toHaveCount(2);
});

it('creates separate groups for different parents', function () {
    $parent1 = User::factory()->create();
    $parent2 = User::factory()->create();
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent1->id);
    $child2->users()->attach($parent2->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolments = collect([$enrolment1->load('child'), $enrolment2->load('child')]);
    $result = $this->action->execute($enrolments);

    expect($result)->toHaveCount(2);
});

it('creates separate groups for different centres', function () {
    $parent = User::factory()->create();
    $centre2 = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent->id);
    $child2->users()->attach($parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => $centre2->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolments = collect([$enrolment1->load('child'), $enrolment2->load('child')]);
    $result = $this->action->execute($enrolments);

    expect($result)->toHaveCount(2);
});

it('skips enrolments without parent', function () {
    $child = Child::factory()->create();

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolments = collect([$enrolment->load('child')]);
    $result = $this->action->execute($enrolments);

    expect($result)->toBeEmpty();
});

it('includes parent and centre information in group', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->create();

    $child->users()->attach($parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => $this->centre->id,
        'product_id' => $this->product->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $enrolments = collect([$enrolment->load('child')]);
    $result = $this->action->execute($enrolments);
    $groupKey = array_key_first($result);

    expect($result[$groupKey]['parent']->id)->toBe($parent->id)
        ->and($result[$groupKey]['centre_id'])->toBe($this->centre->id)
        ->and($result[$groupKey]['tenant_id'])->toBe($this->tenant->id);
});
