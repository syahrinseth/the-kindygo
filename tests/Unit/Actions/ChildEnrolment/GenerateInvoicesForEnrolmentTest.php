<?php

use App\Actions\ChildEnrolment\GenerateInvoicesForEnrolment;
use App\Actions\ChildEnrolment\GenerateInvoicesForEnrolments;
use App\Enums\ChildEnrolmentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Collection;

beforeEach(function () {
    test()->tenant = Tenant::factory()->create();
    test()->centre = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->child = Child::factory()->create();
    test()->product = Product::factory()->create(['tenant_id' => test()->tenant->id]);

    test()->generateInvoicesForEnrolments = mock(GenerateInvoicesForEnrolments::class);
    test()->action = new GenerateInvoicesForEnrolment(test()->generateInvoicesForEnrolments);
});

it('wraps single enrolment in collection and delegates to GenerateInvoicesForEnrolments', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => test()->child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
        'status' => ChildEnrolmentStatus::DRAFT,
    ]);

    $invoice = Invoice::factory()->make(['id' => 1]);
    $expectedCollection = collect([$invoice]);

    test()->generateInvoicesForEnrolments
        ->shouldReceive('execute')
        ->once()
        ->with(\Mockery::on(function ($arg) use ($enrolment) {
            return $arg instanceof Collection
                && $arg->count() === 1
                && $arg->first()->id === $enrolment->id;
        }))
        ->andReturn($expectedCollection);

    $result = test()->action->execute($enrolment);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toBe($expectedCollection);
});

it('returns collection of invoices from delegate', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => test()->child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
    ]);

    $invoices = collect([
        Invoice::factory()->make(['id' => 1]),
        Invoice::factory()->make(['id' => 2]),
    ]);

    test()->generateInvoicesForEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn($invoices);

    $result = test()->action->execute($enrolment);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result->count())->toBe(2);
});

it('works with draft status enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => test()->child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
        'status' => ChildEnrolmentStatus::DRAFT,
    ]);

    test()->generateInvoicesForEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn(collect());

    $result = test()->action->execute($enrolment);

    expect($result)->toBeInstanceOf(Collection::class);
});

it('works with pending status enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => test()->child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
        'status' => ChildEnrolmentStatus::PENDING,
    ]);

    test()->generateInvoicesForEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn(collect());

    $result = test()->action->execute($enrolment);

    expect($result)->toBeInstanceOf(Collection::class);
});

it('works with active status enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'child_id' => test()->child->id,
        'centre_id' => test()->centre->id,
        'product_id' => test()->product->id,
        'tenant_id' => test()->tenant->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
    ]);

    test()->generateInvoicesForEnrolments
        ->shouldReceive('execute')
        ->once()
        ->andReturn(collect());

    $result = test()->action->execute($enrolment);

    expect($result)->toBeInstanceOf(Collection::class);
});
