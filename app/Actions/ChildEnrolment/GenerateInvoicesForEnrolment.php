<?php

namespace App\Actions\ChildEnrolment;

use App\Models\ChildEnrolment;
use Illuminate\Support\Collection;

class GenerateInvoicesForEnrolment
{
    public function __construct(
        protected GenerateInvoicesForEnrolments $generateInvoicesForEnrolments,
    ) {}

    public function execute(ChildEnrolment $enrolment): Collection
    {
        return $this->generateInvoicesForEnrolments->execute(collect([$enrolment]));
    }
}
