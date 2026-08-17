<?php

namespace App\Actions\Registration;

use App\Models\Child;
use App\Models\User;
use App\Support\MalaysianIdentificationNumber;

class CreateChildrenForParentAction
{
    /**
     * Execute the action to create children for a parent.
     */
    public function execute(User $user, ?array $childrenData): void
    {
        // Allow skipping if no children data provided
        if (empty($childrenData)) {
            $user->registration_step = 3;
            $user->updateRegistrationData(3, [
                'children_count' => 0,
                'skipped' => true,
            ]);

            return;
        }

        $createdChildren = [];

        foreach ($childrenData as $childData) {
            $myKidNumber = MalaysianIdentificationNumber::format($childData['mykid_no'] ?? null);
            $childData['mykid_no'] = $myKidNumber;

            // Determine unique identifier for firstOrCreate
            // Priority: mykid_no > combination of first_name + last_name + date_of_birth
            if (! empty($myKidNumber)) {
                // Use MyKID as primary identifier
                $uniqueAttributes = ['mykid_no' => $myKidNumber];
            } else {
                // Fall back to name + DOB combination
                $uniqueAttributes = [
                    'first_name' => $childData['first_name'] ?? null,
                    'last_name' => $childData['last_name'] ?? null,
                    'date_of_birth' => $childData['date_of_birth'] ?? null,
                ];
            }

            // Create or update the child
            $child = Child::updateOrCreate(
                $uniqueAttributes,
                [
                    'first_name' => $childData['first_name'] ?? null,
                    'patronymic' => $childData['patronymic'] ?? null,
                    'last_name' => $childData['last_name'] ?? null,
                    'gender' => $childData['gender'] ?? null,
                    'date_of_birth' => $childData['date_of_birth'] ?? null,
                    'place_of_birth' => $childData['place_of_birth'] ?? null,
                    'race' => $childData['race'] ?? null,
                    'religion' => $childData['religion'] ?? null,
                    'position_of_child' => $childData['position_of_child'] ?? null,
                    'mykid_no' => $childData['mykid_no'] ?? null,
                    'cert_number' => $childData['cert_number'] ?? null,
                ]
            );

            // Establish ChildUser relationship with Parent type
            $user->children()->syncWithoutDetaching([
                $child->id => ['relationship_type' => 'Parent'],
            ]);

            // Establish TenantChild relationship with NEW status
            if ($user->current_tenant_id) {
                $child->tenants()->syncWithoutDetaching([
                    $user->current_tenant_id => ['status' => 'new'],
                ]);
            }

            $createdChildren[] = [
                'id' => $child->id,
                'first_name' => $child->first_name,
                'patronymic' => $child->patronymic,
                'last_name' => $child->last_name,
                'gender' => $child->gender,
                'date_of_birth' => $child->date_of_birth,
                'place_of_birth' => $child->place_of_birth,
                'race' => $child->race,
                'religion' => $child->religion,
                'position_of_child' => $child->position_of_child,
                'mykid_no' => $child->mykid_no,
                'cert_number' => $child->cert_number,
            ];
        }

        // Update registration progress
        $user->registration_step = 3;
        $user->updateRegistrationData(3, [
            'children_count' => count($createdChildren),
            'children' => $createdChildren,
            'skipped' => false,
        ]);
    }
}
