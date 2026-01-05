<?php

namespace App\Actions\ChildEnrolment;

use App\Actions\Invoice\CreateInvoiceForGroup;
use Illuminate\Support\Collection;

class GenerateInvoicesForEnrolments
{
    public function __construct(
        protected GroupEnrolmentsByParentAndCentre $groupEnrolments,
        protected CreateInvoiceForGroup $createInvoiceForGroup,
        protected ActivateEnrolments $activateEnrolments,
    ) {}

    public function execute(Collection $enrolments): Collection
    {
        $invoices = collect();
        $groupedEnrolments = $this->groupEnrolments->execute($enrolments);

        foreach ($groupedEnrolments as $group) {
            $invoice = $this->createInvoiceForGroup->execute($group);

            if ($invoice) {
                $invoices->push($invoice);
                $this->activateEnrolments->execute($group['enrolments']);
            }
        }

        return $invoices;
    }
}
