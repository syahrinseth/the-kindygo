<?php

use App\Actions\ChildEnrolment\GetNextBillingDate;
use App\Enums\ChildEnrolmentBilledEvery;
use Carbon\Carbon;

beforeEach(function () {
    $this->action = new GetNextBillingDate;
    $this->currentDate = Carbon::parse('2026-01-15');
});

it('calculates next daily billing date', function () {
    $result = $this->action->execute($this->currentDate, ChildEnrolmentBilledEvery::DAILY);

    expect($result->toDateString())->toBe('2026-01-16');
});

it('calculates next weekly billing date', function () {
    $result = $this->action->execute($this->currentDate, ChildEnrolmentBilledEvery::WEEKLY);

    expect($result->toDateString())->toBe('2026-01-22');
});

it('calculates next monthly billing date', function () {
    $result = $this->action->execute($this->currentDate, ChildEnrolmentBilledEvery::MONTHLY);

    expect($result->toDateString())->toBe('2026-02-15');
});

it('calculates next quarterly billing date', function () {
    $result = $this->action->execute($this->currentDate, ChildEnrolmentBilledEvery::QUARTERLY);

    expect($result->toDateString())->toBe('2026-04-15');
});

it('calculates next yearly billing date', function () {
    $result = $this->action->execute($this->currentDate, ChildEnrolmentBilledEvery::YEARLY);

    expect($result->toDateString())->toBe('2027-01-15');
});

it('finds next yearly billing date after today', function () {
    $startDate = Carbon::parse('2025-01-15');
    $today = Carbon::parse('2026-01-20');

    $result = $this->action->yearly($startDate, $today);

    expect($result->toDateString())->toBe('2027-01-15');
});

it('finds next quarterly billing date after today', function () {
    $startDate = Carbon::parse('2026-01-15');
    $today = Carbon::parse('2026-04-20');

    $result = $this->action->quarterly($startDate, $today);

    expect($result->toDateString())->toBe('2026-07-15');
});

it('finds next monthly billing date after today', function () {
    $startDate = Carbon::parse('2026-01-15');
    $today = Carbon::parse('2026-02-20');

    $result = $this->action->monthly($startDate, $today);

    expect($result->toDateString())->toBe('2026-03-15');
});

it('finds next weekly billing date after today', function () {
    $startDate = Carbon::parse('2026-01-15');
    $today = Carbon::parse('2026-01-22');

    $result = $this->action->weekly($startDate, $today);

    expect($result->toDateString())->toBe('2026-01-29');
});

it('does not modify original date', function () {
    $originalDate = $this->currentDate->copy();

    $this->action->execute($this->currentDate, ChildEnrolmentBilledEvery::MONTHLY);

    expect($this->currentDate->toDateString())->toBe($originalDate->toDateString());
});
