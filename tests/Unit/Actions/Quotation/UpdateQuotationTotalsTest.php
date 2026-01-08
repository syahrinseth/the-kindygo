<?php

use App\Actions\Quotation\UpdateQuotationTotals;
use App\Enums\QuotationStatus;
use App\Models\Centre;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\{actingAs};

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->quotation = Quotation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'status' => QuotationStatus::DRAFT,
        'total_items' => 0,
        'total_amount' => 0,
        'total' => 0,
    ]);

    $this->action = new UpdateQuotationTotals();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('calculates totals for single item', function () {
    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(1)
        ->and($this->quotation->total_amount)->toBe(50000)
        ->and($this->quotation->total)->toBe(50000);
});

it('sums multiple items correctly', function () {
    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
    ]);

    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
    ]);

    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(3)
        ->and($this->quotation->total_amount)->toBe(100000)
        ->and($this->quotation->total)->toBe(100000);
});

it('handles items with discounts', function () {
    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 50000,
        'quantity' => 1,
        'discount' => 5000,
        'total' => 45000, // price - discount
    ]);

    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 30000,
        'quantity' => 1,
        'discount' => 3000,
        'total' => 27000, // price - discount
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(2)
        ->and($this->quotation->total_amount)->toBe(72000); // 45000 + 27000
});

it('handles empty quotation', function () {
    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(0)
        ->and($this->quotation->total_amount)->toBe(0)
        ->and($this->quotation->total)->toBe(0);
});

it('updates after item changes', function () {
    $item = QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(1)
        ->and($this->quotation->total_amount)->toBe(50000);

    // Update item - need to recalculate total manually since we're updating directly
    $item->update([
        'price' => 60000,
        'total' => 60000, // Update total as well
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_amount)->toBe(60000);
});

it('recalculates when items are deleted', function () {
    $item1 = QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
    ]);

    $item2 = QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(2)
        ->and($this->quotation->total_amount)->toBe(80000);

    // Delete one item
    $item1->delete();

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(1)
        ->and($this->quotation->total_amount)->toBe(30000);
});

it('handles items with quantities greater than 1', function () {
    QuotationItem::factory()->create([
        'quotation_id' => $this->quotation->id,
        'price' => 50000,
        'quantity' => 3,
        'discount' => 0,
        'total' => 150000, // price * quantity
    ]);

    $this->action->execute($this->quotation);
    $this->quotation->refresh();

    expect($this->quotation->total_items)->toBe(1) // 1 line item
        ->and($this->quotation->total_amount)->toBe(150000);
});
