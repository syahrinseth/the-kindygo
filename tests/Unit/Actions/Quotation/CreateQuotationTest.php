<?php

use App\Actions\Quotation\CreateQuotation;
use App\Enums\QuotationStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\{actingAs, assertDatabaseHas};

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->action = new CreateQuotation();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('creates quotation with correct attributes', function () {
    $date = Carbon::parse('2026-01-08');

    $quotation = $this->action->execute(
        $this->tenant->id,
        $this->centre->id,
        $this->user->id,
        $this->child->id,
        $date
    );

    expect($quotation->tenant_id)->toBe($this->tenant->id)
        ->and($quotation->centre_id)->toBe($this->centre->id)
        ->and($quotation->user_id)->toBe($this->user->id)
        ->and($quotation->child_id)->toBe($this->child->id)
        ->and($quotation->date->toDateString())->toBe('2026-01-08');
});

it('auto-generates QUO/ number format', function () {
    $date = Carbon::parse('2026-01-08');

    $quotation = $this->action->execute(
        $this->tenant->id,
        $this->centre->id,
        $this->user->id,
        null,
        $date
    );

    expect($quotation->number)->toStartWith('QUO/')
        ->and($quotation->number)->toContain('/2026/')
        ->and($quotation->number)->toMatch('/^QUO\/[A-Z0-9]+\/2026\/\d{4}$/');
});

it('defaults valid_until to 30 days from date', function () {
    $date = Carbon::parse('2026-01-08');

    $quotation = $this->action->execute(
        $this->tenant->id,
        $this->centre->id,
        $this->user->id,
        null,
        $date
    );

    expect($quotation->valid_until->toDateString())->toBe('2026-02-07'); // 30 days later
});

it('initializes totals to zero', function () {
    $date = Carbon::parse('2026-01-08');

    $quotation = $this->action->execute(
        $this->tenant->id,
        $this->centre->id,
        $this->user->id,
        null,
        $date
    );

    expect($quotation->total_items)->toBe(0)
        ->and($quotation->total_amount)->toBe(0)
        ->and($quotation->total)->toBe(0);
});

it('sets DRAFT status by default', function () {
    $date = Carbon::parse('2026-01-08');

    $quotation = $this->action->execute(
        $this->tenant->id,
        $this->centre->id,
        $this->user->id,
        null,
        $date
    );

    expect($quotation->status)->toBe(QuotationStatus::DRAFT);
});

it('accepts custom valid_until date', function () {
    $date = Carbon::parse('2026-01-08');
    $validUntil = Carbon::parse('2026-03-08');

    $quotation = $this->action->execute(
        $this->tenant->id,
        $this->centre->id,
        $this->user->id,
        null,
        $date,
        $validUntil
    );

    expect($quotation->valid_until->toDateString())->toBe('2026-03-08');
});
