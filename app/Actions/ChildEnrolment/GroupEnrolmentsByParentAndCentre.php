<?php

namespace App\Actions\ChildEnrolment;

use Illuminate\Support\Collection;

class GroupEnrolmentsByParentAndCentre
{
    public function execute(Collection $enrolments): array
    {
        $grouped = [];

        foreach ($enrolments as $enrolment) {
            $parent = $enrolment->child->users()->first();
            if (! $parent) {
                continue;
            }

            $groupKey = $enrolment->tenant_id.'_'.$parent->id.'_'.$enrolment->centre_id;

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'parent' => $parent,
                    'centre_id' => $enrolment->centre_id,
                    'tenant_id' => $enrolment->tenant_id,
                    'enrolments' => collect(),
                ];
            }

            $grouped[$groupKey]['enrolments']->push($enrolment);
        }

        return $grouped;
    }
}
