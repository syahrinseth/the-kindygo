<?php

namespace App\Actions\ChildEnrolment;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\ChildEnrolment;
use Carbon\Carbon;

class GetNextBillingPeriodStart
{
    public function __construct(
        protected GetNextBillingDate $getNextBillingDate,
    ) {}

    public function execute(ChildEnrolment $enrolment): ?Carbon
    {
        $startDate = Carbon::parse($enrolment->date_start);
        $today = now();
        $endDate = $enrolment->date_end ? Carbon::parse($enrolment->date_end) : null;

        if ($today->lt($startDate)) {
            if ($endDate && $startDate->gt($endDate)) {
                return null;
            }

            return $startDate;
        }

        if ($enrolment->billed_every === ChildEnrolmentBilledEvery::ONE_TIME) {
            $existingItem = $enrolment->invoiceItems()->first();
            if ($existingItem) {
                return null;
            }

            return (! $endDate || $startDate->lte($endDate)) ? $startDate : null;
        }

        $nextDate = match ($enrolment->billed_every) {
            ChildEnrolmentBilledEvery::YEARLY => $this->getNextBillingDate->yearly($startDate, $today),
            ChildEnrolmentBilledEvery::QUARTERLY => $this->getNextBillingDate->quarterly($startDate, $today),
            ChildEnrolmentBilledEvery::MONTHLY => $this->getNextBillingDate->monthly($startDate, $today),
            ChildEnrolmentBilledEvery::WEEKLY => $this->getNextBillingDate->weekly($startDate, $today),
            ChildEnrolmentBilledEvery::DAILY => $today->copy()->addDay(),
            default => null,
        };

        if ($nextDate && $endDate && $nextDate->gt($endDate)) {
            return null;
        }

        return $nextDate;
    }
}
