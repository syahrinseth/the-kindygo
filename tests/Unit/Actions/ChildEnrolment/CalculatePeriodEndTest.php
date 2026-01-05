<?php

use App\Actions\ChildEnrolment\CalculatePeriodEnd;
use App\Enums\ChildEnrolmentBilledEvery;
use Carbon\Carbon;

beforeEach(function () {
    $this->action = new CalculatePeriodEnd;
    $this->baseDate = Carbon::parse('2026-01-01');
});

it('calculates daily period end correctly', function () {
    $result = $this->action->execute($this->baseDate, ChildEnrolmentBilledEvery::DAILY);

    expect($result->toDateString())->toBe('2026-01-01');
});

it('calculates weekly period end correctly', function () {
    $result = $this->action->execute($this->baseDate, ChildEnrolmentBilledEvery::WEEKLY);

    expect($result->toDateString())->toBe('2026-01-07');
});

it('calculates monthly period end correctly', function () {
    $result = $this->action->execute($this->baseDate, ChildEnrolmentBilledEvery::MONTHLY);

    expect($result->toDateString())->toBe('2026-01-31');
});

it('calculates quarterly period end correctly', function () {
    $result = $this->action->execute($this->baseDate, ChildEnrolmentBilledEvery::QUARTERLY);

    expect($result->toDateString())->toBe('2026-03-31');
});

it('calculates yearly period end correctly', function () {
    $result = $this->action->execute($this->baseDate, ChildEnrolmentBilledEvery::YEARLY);

    expect($result->toDateString())->toBe('2026-12-31');
});

it('handles leap year for monthly billing', function () {
    $leapYearDate = Carbon::parse('2024-01-31');
    $result = $this->action->execute($leapYearDate, ChildEnrolmentBilledEvery::MONTHLY);

    // Adding 1 month to Jan 31 gives Feb 31 (which becomes Mar 2), then subDay gives Mar 1
    expect($result->toDateString())->toBe('2024-03-01');
});

it('does not modify original date', function () {
    $originalDate = $this->baseDate->copy();

    $this->action->execute($this->baseDate, ChildEnrolmentBilledEvery::MONTHLY);

    expect($this->baseDate->toDateString())->toBe($originalDate->toDateString());
});
