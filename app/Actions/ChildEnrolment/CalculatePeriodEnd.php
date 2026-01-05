<?php

namespace App\Actions\ChildEnrolment;

use App\Enums\ChildEnrolmentBilledEvery;
use Carbon\Carbon;

class CalculatePeriodEnd
{
    public function execute(Carbon $periodStart, ChildEnrolmentBilledEvery $billedEvery): Carbon
    {
        return match ($billedEvery) {
            ChildEnrolmentBilledEvery::DAILY => $periodStart->copy(),
            ChildEnrolmentBilledEvery::WEEKLY => $periodStart->copy()->addWeek()->subDay(),
            ChildEnrolmentBilledEvery::MONTHLY => $periodStart->copy()->addMonth()->subDay(),
            ChildEnrolmentBilledEvery::QUARTERLY => $periodStart->copy()->addMonths(3)->subDay(),
            ChildEnrolmentBilledEvery::YEARLY => $periodStart->copy()->addYear()->subDay(),
            default => $periodStart->copy(),
        };
    }
}
