<?php

namespace App\Actions\ChildEnrolment;

use App\Enums\ChildEnrolmentBilledEvery;
use Carbon\Carbon;

class GetNextBillingDate
{
    public function execute(Carbon $currentDate, ChildEnrolmentBilledEvery $billedEvery): Carbon
    {
        return match ($billedEvery) {
            ChildEnrolmentBilledEvery::DAILY => $currentDate->copy()->addDay(),
            ChildEnrolmentBilledEvery::WEEKLY => $currentDate->copy()->addWeek(),
            ChildEnrolmentBilledEvery::MONTHLY => $currentDate->copy()->addMonth(),
            ChildEnrolmentBilledEvery::QUARTERLY => $currentDate->copy()->addMonths(3),
            ChildEnrolmentBilledEvery::YEARLY => $currentDate->copy()->addYear(),
            default => $currentDate->copy()->addMonth(),
        };
    }

    public function yearly(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addYear();
        }

        return $nextDate;
    }

    public function quarterly(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addMonths(3);
        }

        return $nextDate;
    }

    public function monthly(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addMonth();
        }

        return $nextDate;
    }

    public function weekly(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addWeek();
        }

        return $nextDate;
    }
}
