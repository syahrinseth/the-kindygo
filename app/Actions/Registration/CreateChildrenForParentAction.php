<?php

namespace App\Actions\Registration;

use App\Models\Child;
use App\Models\User;
use App\Support\MalaysianIdentificationNumber;
use Illuminate\Support\Facades\DB;

class CreateChildrenForParentAction
{
    /**
     * Execute the action to create children for a parent.
     */
    public function execute(User $user, ?array $childrenData): void
    {
        DB::transaction(function () use ($user, $childrenData): void {
            $registrationUser = User::query()->lockForUpdate()->findOrFail($user->id);

            // Allow skipping if no children data provided
            if (empty($childrenData)) {
                $registrationUser->registration_step = 3;
                $registrationUser->updateRegistrationData(3, [
                    'children_count' => 0,
                    'skipped' => true,
                ]);

                return;
            }

            $existingChildren = $registrationUser->getRegistrationData('step_3.children');
            $createdChildren = [];

            foreach ($childrenData as $index => $childData) {
                $myKidNumber = MalaysianIdentificationNumber::format($childData['mykid_no'] ?? null);
                $childData['mykid_no'] = $myKidNumber;

                $attributes = [
                    'first_name' => $childData['first_name'] ?? null,
                    'patronymic' => $childData['patronymic'] ?? null,
                    'last_name' => $childData['last_name'] ?? null,
                    'gender' => $childData['gender'] ?? null,
                    'date_of_birth' => $childData['date_of_birth'] ?? null,
                    'place_of_birth' => $childData['place_of_birth'] ?? null,
                    'race' => $childData['race'] ?? null,
                    'religion' => $childData['religion'] ?? null,
                    'position_of_child' => $childData['position_of_child'] ?? null,
                    'mykid_no' => $myKidNumber,
                    'cert_number' => $childData['cert_number'] ?? null,
                ];

                $previousChild = $this->findPreviousChild($registrationUser, $existingChildren, $index);

                $child = $this->shouldReusePreviousChild($previousChild, $myKidNumber)
                    ? tap($previousChild)->update($attributes)
                    : ($myKidNumber
                        ? Child::updateOrCreate(['mykid_no' => $myKidNumber], $attributes)
                        : Child::create($attributes));

                // Establish ChildUser relationship with Parent type
                $registrationUser->children()->syncWithoutDetaching([
                    $child->id => ['relationship_type' => 'Parent'],
                ]);

                // Establish TenantChild relationship with NEW status
                if ($registrationUser->current_tenant_id) {
                    $child->tenants()->syncWithoutDetaching([
                        $registrationUser->current_tenant_id => ['status' => 'new'],
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
            $registrationUser->registration_step = 3;
            $registrationUser->updateRegistrationData(3, [
                'children_count' => count($createdChildren),
                'children' => $createdChildren,
                'skipped' => false,
            ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $existingChildren
     */
    private function findPreviousChild(User $user, ?array $existingChildren, int|string $index): ?Child
    {
        $childId = $existingChildren[$index]['id'] ?? null;

        return is_int($childId) || ctype_digit((string) $childId)
            ? $user->children()->find($childId)
            : null;
    }

    private function shouldReusePreviousChild(?Child $previousChild, ?string $myKidNumber): bool
    {
        if (! $previousChild) {
            return false;
        }

        return ! $myKidNumber
            || ! $previousChild->mykid_no
            || $previousChild->mykid_no === $myKidNumber;
    }
}
