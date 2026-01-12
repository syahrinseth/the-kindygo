<?php

use App\Actions\ChildEnrolment\ActivateEnrolments;
use App\Actions\ChildEnrolment\GenerateInvoicesForEnrolments;
use App\Actions\ChildEnrolment\GroupEnrolmentsByParentAndCentre;
use App\Actions\Invoice\CreateInvoiceForGroup;
use App\Enums\ChildEnrolmentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

beforeEach(function () {
    test()->tenant = Tenant::factory()->create();
    test()->centre = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    test()->groupEnrolments = mock(GroupEnrolmentsByParentAndCentre::class);
    test()->createInvoiceForGroup = mock(CreateInvoiceForGroup::class);
    test()->activateEnrolments = mock(ActivateEnrolments::class);

    test()->action = new GenerateInvoicesForEnrolments(
        test()->groupEnrolments,
        test()->createInvoiceForGroup,
        test()->activateEnrolments
    );
});

it('groups enrolments and creates invoices for each group', function () {
    $parent = User::factory()->create();
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent->id);
    $child2->users()->attach($parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
        'status' => ChildEnrolmentStatus::DRAFT,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
        'status' => ChildEnrolmentStatus::DRAFT,
    ]);

    $enrolments = collect([$enrolment1, $enrolment2]);

    $groupedEnrolments = [
        'group_1' => [
            'parent' => $parent,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => $enrolments,
        ],
    ];

    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
    ]);

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->with($enrolments)
        ->andReturn($groupedEnrolments);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_1'])
        ->andReturn($invoice);

    test()->activateEnrolments
        ->shouldReceive('execute')
        ->once()
        ->with($enrolments);

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(1)
        ->and($result->first())->toBe($invoice);
});

it('creates separate invoices for multiple groups', function () {
    $parent1 = User::factory()->create();
    $parent2 = User::factory()->create();
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent1->id);
    $child2->users()->attach($parent2->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolments = collect([$enrolment1, $enrolment2]);

    $groupedEnrolments = [
        'group_1' => [
            'parent' => $parent1,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => collect([$enrolment1]),
        ],
        'group_2' => [
            'parent' => $parent2,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => collect([$enrolment2]),
        ],
    ];

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
    ]);

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn($groupedEnrolments);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_1'])
        ->andReturn($invoice1);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_2'])
        ->andReturn($invoice2);

    test()->activateEnrolments
        ->shouldReceive('execute')
        ->twice();

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(2);
});

it('only activates enrolments if invoice is created successfully', function () {
    $parent = User::factory()->create();
    $child = Child::factory()->create();
    $child->users()->attach($parent->id);

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolments = collect([$enrolment]);

    $groupedEnrolments = [
        'group_1' => [
            'parent' => $parent,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => $enrolments,
        ],
    ];

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn($groupedEnrolments);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->andReturn(null);

    test()->activateEnrolments
        ->shouldNotReceive('execute');

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(0);
});

it('handles empty enrolment collection', function () {
    $enrolments = collect();

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->with($enrolments)
        ->andReturn([]);

    test()->createInvoiceForGroup
        ->shouldNotReceive('execute');

    test()->activateEnrolments
        ->shouldNotReceive('execute');

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(0);
});

it('returns empty collection when no groups are found', function () {
    $child = Child::factory()->create();

    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => $child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolments = collect([$enrolment]);

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn([]);

    test()->createInvoiceForGroup
        ->shouldNotReceive('execute');

    test()->activateEnrolments
        ->shouldNotReceive('execute');

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->isEmpty())->toBeTrue();
});

it('processes multiple groups and skips groups with null invoices', function () {
    $parent1 = User::factory()->create();
    $parent2 = User::factory()->create();
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent1->id);
    $child2->users()->attach($parent2->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolments = collect([$enrolment1, $enrolment2]);

    $groupedEnrolments = [
        'group_1' => [
            'parent' => $parent1,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => collect([$enrolment1]),
        ],
        'group_2' => [
            'parent' => $parent2,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => collect([$enrolment2]),
        ],
    ];

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
    ]);

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn($groupedEnrolments);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_1'])
        ->andReturn($invoice1);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_2'])
        ->andReturn(null);

    test()->activateEnrolments
        ->shouldReceive('execute')
        ->once();

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(1)
        ->and($result->first())->toBe($invoice1);
});

it('collects all successfully created invoices', function () {
    $parent = User::factory()->create();
    $centre2 = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->users()->attach($parent->id);
    $child2->users()->attach($parent->id);

    $enrolment1 = ChildEnrolment::factory()->create([
        'child_id' => $child1->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'child_id' => $child2->id,
        'centre_id' => $centre2->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $enrolments = collect([$enrolment1, $enrolment2]);

    $groupedEnrolments = [
        'group_1' => [
            'parent' => $parent,
            'centre_id' => test()->centre->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => collect([$enrolment1]),
        ],
        'group_2' => [
            'parent' => $parent,
            'centre_id' => $centre2->id,
            'tenant_id' => test()->tenant->id,
            'enrolments' => collect([$enrolment2]),
        ],
    ];

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => $centre2->id,
    ]);

    test()->groupEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn($groupedEnrolments);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_1'])
        ->andReturn($invoice1);

    test()->createInvoiceForGroup
        ->shouldReceive('execute')
        ->once()
        ->with($groupedEnrolments['group_2'])
        ->andReturn($invoice2);

    test()->activateEnrolments
        ->shouldReceive('execute')
        ->twice();

    $result = test()->action->execute($enrolments);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(2)
        ->and($result->contains($invoice1))->toBeTrue()
        ->and($result->contains($invoice2))->toBeTrue();
});
