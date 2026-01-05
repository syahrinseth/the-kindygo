<?php

namespace App\Services;

use App\Actions\ChildEnrolment\GenerateInvoicesForEnrolment as GenerateInvoicesForEnrolmentAction;
use App\Actions\ChildEnrolment\GenerateInvoicesForEnrolments as GenerateInvoicesForEnrolmentsAction;
use App\Actions\ChildEnrolment\GetNextBillingPeriodStart;
use App\Actions\ChildEnrolment\GetRelatedEnrolments as GetRelatedEnrolmentsAction;
use App\Actions\ChildEnrolment\ShouldGenerateInvoices as ShouldGenerateInvoicesAction;
use App\Models\ChildEnrolment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChildEnrolmentInvoiceService
{
    public function __construct(
        protected GenerateInvoicesForEnrolmentAction $generateInvoicesForEnrolmentAction,
        protected GenerateInvoicesForEnrolmentsAction $generateInvoicesForEnrolmentsAction,
        protected GetRelatedEnrolmentsAction $getRelatedEnrolmentsAction,
        protected GetNextBillingPeriodStart $getNextBillingPeriodStart,
        protected ShouldGenerateInvoicesAction $shouldGenerateInvoicesAction,
    ) {}

    public function generateInvoicesForEnrolment(ChildEnrolment $enrolment): Collection
    {
        return $this->generateInvoicesForEnrolmentAction->execute($enrolment);
    }

    public function generateInvoicesForEnrolments(Collection $enrolments): Collection
    {
        return $this->generateInvoicesForEnrolmentsAction->execute($enrolments);
    }

    public function getRelatedEnrolments(ChildEnrolment $enrolment): ?Collection
    {
        return $this->getRelatedEnrolmentsAction->execute($enrolment);
    }

    public function getNextBillingPeriodStart(ChildEnrolment $enrolment): ?Carbon
    {
        return $this->getNextBillingPeriodStart->execute($enrolment);
    }

    public function shouldGenerateInvoices(ChildEnrolment $enrolment, int $daysAhead): bool
    {
        return $this->shouldGenerateInvoicesAction->execute($enrolment, $daysAhead);
    }

    public function hasExistingInvoiceItemForPeriod(ChildEnrolment $enrolment, Carbon $periodStart): bool
    {
        return $enrolment->invoiceItems()
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();
    }
}
