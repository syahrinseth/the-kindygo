<?php

namespace App\Actions\ChildEnrolment;

use App\Enums\ChildEnrolmentStatus;
use Illuminate\Support\Collection;

class ActivateEnrolments
{
    /**
     * Activate enrolments when invoices are generated.
     * Only activates enrolments in DRAFT, PENDING, or INACTIVE status.
     */
    public function execute(Collection $enrolments): void
    {
        $allowedStatuses = [
            ChildEnrolmentStatus::DRAFT,
            ChildEnrolmentStatus::PENDING,
            ChildEnrolmentStatus::INACTIVE,
        ];

        foreach ($enrolments as $enrolment) {
            if (in_array($enrolment->status, $allowedStatuses)) {
                $enrolment->update(['status' => ChildEnrolmentStatus::ACTIVE]);
            }
        }
    }
}
