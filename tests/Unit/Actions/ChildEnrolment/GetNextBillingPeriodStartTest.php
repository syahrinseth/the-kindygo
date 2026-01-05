<?php

use App\Actions\ChildEnrolment\GetNextBillingDate;
use App\Actions\ChildEnrolment\GetNextBillingPeriodStart;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\ChildEnrolment;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-23 08:00:00');
    $this->getNextBillingDate = new GetNextBillingDate;
    $this->action = new GetNextBillingPeriodStart($this->getNextBillingDate);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns start date when today is before start date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-02-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::MONTHLY;

    $result = $this->action->execute($enrolment);

    expect($result->toDateString())->toBe('2026-02-01');
});

it('returns null when start date is after end date and today is before start', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-02-01';
    $enrolment->date_end = '2026-01-31';
    $enrolment->billed_every = ChildEnrolmentBilledEvery::MONTHLY;

    $result = $this->action->execute($enrolment);

    expect($result)->toBeNull();
});

it('returns null for one-time billing with existing invoice item', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-01-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::ONE_TIME;
    $enrolment->shouldReceive('invoiceItems->first')->andReturn(true);

    $result = $this->action->execute($enrolment);

    expect($result)->toBeNull();
});

it('returns start date for one-time billing without invoice item', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-01-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::ONE_TIME;
    $enrolment->shouldReceive('invoiceItems->first')->andReturn(null);

    $result = $this->action->execute($enrolment);

    expect($result->toDateString())->toBe('2026-01-01');
});

it('returns null for one-time billing when start date is after end date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-02-01';
    $enrolment->date_end = '2026-01-31';
    $enrolment->billed_every = ChildEnrolmentBilledEvery::ONE_TIME;
    $enrolment->shouldReceive('invoiceItems->first')->andReturn(null);

    $result = $this->action->execute($enrolment);

    expect($result)->toBeNull();
});

it('calculates next yearly billing date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2025-01-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::YEARLY;

    $result = $this->action->execute($enrolment);

    expect($result->toDateString())->toBe('2027-01-01');
});

it('calculates next quarterly billing date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2025-10-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::QUARTERLY;

    $result = $this->action->execute($enrolment);

    // Next quarterly billing from Oct 1 is Jan 1, then April 1
    expect($result->toDateString())->toBe('2026-04-01');
});

it('calculates next monthly billing date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2025-12-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::MONTHLY;

    $result = $this->action->execute($enrolment);

    expect($result->toDateString())->toBe('2026-02-01');
});

it('calculates next weekly billing date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-01-06';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::WEEKLY;

    $result = $this->action->execute($enrolment);

    expect($result->toDateString())->toBe('2026-01-20');
});

it('returns tomorrow for daily billing', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2026-01-01';
    $enrolment->date_end = null;
    $enrolment->billed_every = ChildEnrolmentBilledEvery::DAILY;

    $result = $this->action->execute($enrolment);

    expect($result->toDateString())->toBe('2026-01-16');
});

it('returns null when next billing date is after end date', function () {
    $enrolment = mock(ChildEnrolment::class)->makePartial();
    $enrolment->date_start = '2025-12-01';
    $enrolment->date_end = '2026-01-20';
    $enrolment->billed_every = ChildEnrolmentBilledEvery::MONTHLY;

    $result = $this->action->execute($enrolment);

    expect($result)->toBeNull();
});
