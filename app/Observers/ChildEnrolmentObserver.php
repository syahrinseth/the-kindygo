<?php

namespace App\Observers;

use App\Models\ChildEnrolment;
use App\Services\ChildEnrolmentInvoiceService;
use Illuminate\Support\Facades\Auth;

class ChildEnrolmentObserver
{
    protected ChildEnrolmentInvoiceService $invoiceService;

    public function __construct(ChildEnrolmentInvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function creating(ChildEnrolment $enrolment): void
    {
        if (empty($enrolment->tenant_id)) {
            // Assign tenant_id before creating the enrolment
            $enrolment->tenant_id = Auth::user()?->currentTenant()?->id ?? 0;
        }

        // Calculate next_bill_date for new enrolments
        $this->updateNextBillDate($enrolment);
    }

    public function updating(ChildEnrolment $enrolment): void
    {
        // Only recalculate if billing-related fields have changed
        if ($enrolment->isDirty(['billed_every', 'date_start', 'date_end'])) {
            $this->updateNextBillDate($enrolment);
        }
    }

    /**
     * Update the next_bill_date for the given enrolment.
     */
    protected function updateNextBillDate(ChildEnrolment $enrolment): void
    {
        $nextBillDate = $this->invoiceService->getNextBillingPeriodStart($enrolment);
        $enrolment->next_bill_date = $nextBillDate;
    }
}
