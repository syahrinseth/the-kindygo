<?php

namespace App\Actions\ChildEnrolment;

use App\Enums\ChildEnrolmentStatus;
use App\Models\ChildEnrolment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GetRelatedEnrolments
{
    public function execute(ChildEnrolment $enrolment): ?Collection
    {
        $parent = $enrolment->child->users()->first();
        if (! $parent) {
            return null;
        }

        $groupedEnrolments = ChildEnrolment::where('tenant_id', $enrolment->tenant_id)
            ->where('centre_id', $enrolment->centre_id)
            ->where('status', ChildEnrolmentStatus::ACTIVE)
            ->whereHas('child.users', function ($query) use ($parent) {
                $query->where('users.id', $parent->id);
            })
            ->get();

        $enrolmentsNeedingInvoice = $groupedEnrolments->filter(function ($enrolment) {
            if ($enrolment->date_end && Carbon::parse($enrolment->date_end)->lt(Carbon::now())) {
                return false;
            }

            $getNextBillingPeriodStart = app(GetNextBillingPeriodStart::class);
            $nextBillingDate = $getNextBillingPeriodStart->execute($enrolment);

            if (! $nextBillingDate) {
                return false;
            }

            $existingItem = $enrolment->invoiceItems()
                ->whereDate('period_start', '>=', $nextBillingDate->toDateString())
                ->exists();

            return ! $existingItem;
        });

        return $enrolmentsNeedingInvoice->isEmpty() ? null : $enrolmentsNeedingInvoice;
    }
}
