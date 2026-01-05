<?php

namespace App\Actions\ChildEnrolment;

use App\Models\ChildEnrolment;
use Carbon\Carbon;

class ShouldGenerateInvoices
{
    public function __construct(
        protected GetNextBillingPeriodStart $getNextBillingPeriodStart,
    ) {}

    public function execute(ChildEnrolment $enrolment, int $daysAhead): bool
    {
        $parent = $enrolment->child->users()->first();
        if (! $parent) {
            return false;
        }

        if ($enrolment->date_end && Carbon::parse($enrolment->date_end)->lt(Carbon::now())) {
            return false;
        }

        $nextBillingPeriodStart = $this->getNextBillingPeriodStart->execute($enrolment);

        if (! $nextBillingPeriodStart) {
            return false;
        }

        if ($enrolment->date_end && $nextBillingPeriodStart->gt(Carbon::parse($enrolment->date_end))) {
            return false;
        }

        $billUntilDate = Carbon::now()->addDays($daysAhead);
        $shouldBill = $nextBillingPeriodStart->lte($billUntilDate);

        if (! $shouldBill) {
            return false;
        }

        return ! $this->hasExistingInvoiceItemForPeriod($enrolment, $nextBillingPeriodStart);
    }

    private function hasExistingInvoiceItemForPeriod(ChildEnrolment $enrolment, Carbon $periodStart): bool
    {
        return $enrolment->invoiceItems()
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();
    }
}
