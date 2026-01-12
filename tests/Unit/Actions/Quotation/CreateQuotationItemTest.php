<?php

use App\Actions\Quotation\CreateQuotationItem;
use App\Enums\QuotationStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->quotation = Quotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'child_id' => $this->child->id,
        'status' => QuotationStatus::DRAFT,
    ]);

    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Monthly Tuition',
    ]);

    $this->productPrice = ProductPrice::factory()->create([
        'product_id' => $this->product->id,
        'price' => 50000, // RM500.00
    ]);

    // Attach the price to the centre using the pivot table
    $this->productPrice->centres()->attach($this->centre->id);

    $this->enrolment = ChildEnrolment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
    ]);

    $this->action = new CreateQuotationItem;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates quotation item with basic details', function () {
    $periodStart = Carbon::parse('2026-02-01');
    $periodEnd = Carbon::parse('2026-02-28');

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        $periodEnd
    );

    expect($item->quotation_id)->toBe($this->quotation->id)
        ->and($item->product_id)->toBe($this->product->id)
        ->and($item->child_id)->toBe($this->child->id)
        ->and($item->child_enrolment_id)->toBe($this->enrolment->id)
        ->and($item->name)->toBe('Monthly Tuition')
        ->and($item->price)->toBe(50000)
        ->and($item->period_start->toDateString())->toBe('2026-02-01')
        ->and($item->period_end->toDateString())->toBe('2026-02-28');
});

it('gets price from centre product price', function () {
    $periodStart = Carbon::parse('2026-02-01');

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        null
    );

    expect($item->price)->toBe(50000);
});

it('calculates total with quantity', function () {
    $periodStart = Carbon::parse('2026-02-01');

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        null
    );

    expect($item->quantity)->toBe(1)
        ->and($item->total)->toBe(50000); // price * quantity
});

it('applies discount correctly', function () {
    $periodStart = Carbon::parse('2026-02-01');

    // Manually update item with discount after creation
    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        null
    );

    $item->update(['discount' => 5000]); // RM50 discount
    $item->refresh();

    // Total should be (price * quantity) - (discount * quantity)
    expect($item->total)->toBe(45000); // 50000 - 5000
});

it('formats period in description for date range', function () {
    $periodStart = Carbon::parse('2026-02-01');
    $periodEnd = Carbon::parse('2026-02-28');

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        $periodEnd
    );

    expect($item->description)->toContain('Feb 1')
        ->and($item->description)->toContain('Feb 28, 2026');
});

it('formats period in description for single day', function () {
    $periodStart = Carbon::parse('2026-02-15');

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        null
    );

    expect($item->description)->toContain('Feb 15, 2026')
        ->and($item->description)->not->toContain(' - ');
});

it('includes custom notes in description', function () {
    $periodStart = Carbon::parse('2026-02-01');
    $notes = 'Early bird discount applied';

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        null,
        $notes
    );

    expect($item->description)->toContain($notes);
});

it('links product and child', function () {
    $periodStart = Carbon::parse('2026-02-01');

    $item = $this->action->execute(
        $this->quotation,
        $this->product,
        $this->enrolment,
        $periodStart,
        null
    );

    expect($item->product)->not->toBeNull()
        ->and($item->product->id)->toBe($this->product->id)
        ->and($item->child)->not->toBeNull()
        ->and($item->child->id)->toBe($this->child->id);
});
