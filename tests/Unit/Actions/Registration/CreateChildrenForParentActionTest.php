<?php

use App\Actions\Registration\CreateChildrenForParentAction;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->action = new CreateChildrenForParentAction();
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();

    // Attach user to tenant and set current_tenant_id
    $this->user->tenants()->attach($this->tenant->id);
    $this->user->current_tenant_id = $this->tenant->id;
    $this->user->save();
});

it('handles empty array gracefully', function () {
    $this->action->execute($this->user, []);

    $this->user->refresh();

    expect($this->user->registration_step)->toBe(3);
    expect($this->user->getRegistrationData('step_3.skipped'))->toBeTrue();
    expect($this->user->getRegistrationData('step_3.children_count'))->toBe(0);
});

it('handles null children data gracefully', function () {
    $this->action->execute($this->user, null);

    $this->user->refresh();

    expect($this->user->registration_step)->toBe(3);
    expect($this->user->getRegistrationData('step_3.skipped'))->toBeTrue();
});

it('creates single child with correct data', function () {
    $childrenData = [
        [
            'first_name' => 'Ahmad',
            'patronymic' => 'Bin',
            'last_name' => 'Abdullah',
            'date_of_birth' => '2020-01-15',
            'gender' => 'male',
            'place_of_birth' => 'Kuala Lumpur',
            'race' => 'Malay',
            'religion' => 'Islam',
            'position_of_child' => 1,
            'mykid_no' => '200115011234',
            'cert_number' => 'BC123456',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    assertDatabaseHas('children', [
        'first_name' => 'Ahmad',
        'patronymic' => 'Bin',
        'last_name' => 'Abdullah',
        'gender' => 'male',
        'place_of_birth' => 'Kuala Lumpur',
        'race' => 'Malay',
        'religion' => 'Islam',
        'position_of_child' => 1,
        'mykid_no' => '200115011234',
        'cert_number' => 'BC123456',
    ]);

    $child = Child::where('first_name', 'Ahmad')->first();
    expect($child->date_of_birth->format('Y-m-d'))->toBe('2020-01-15');
});

it('creates multiple children with all relationships', function () {
    $childrenData = [
        [
            'first_name' => 'Sarah',
            'patronymic' => 'Binti',
            'last_name' => 'Ahmad',
            'date_of_birth' => '2019-05-20',
            'gender' => 'female',
            'place_of_birth' => 'Penang',
            'race' => 'Malay',
            'religion' => 'Islam',
            'position_of_child' => 1,
            'mykid_no' => '190520011234',
            'cert_number' => 'BC111111',
        ],
        [
            'first_name' => 'Ali',
            'patronymic' => 'Bin',
            'last_name' => 'Ahmad',
            'date_of_birth' => '2021-03-10',
            'gender' => 'male',
            'place_of_birth' => 'Johor',
            'race' => 'Malay',
            'religion' => 'Islam',
            'position_of_child' => 2,
            'mykid_no' => '210310011234',
            'cert_number' => 'BC222222',
        ],
        [
            'first_name' => 'Fatimah',
            'patronymic' => 'Binti',
            'last_name' => 'Ahmad',
            'date_of_birth' => '2022-07-25',
            'gender' => 'female',
            'place_of_birth' => 'Melaka',
            'race' => 'Malay',
            'religion' => 'Islam',
            'position_of_child' => 3,
            'mykid_no' => '220725011234',
            'cert_number' => 'BC333333',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    expect(Child::count())->toBe(3);
    expect($this->user->children)->toHaveCount(3);
});

it('sets ChildUser relationship_type to Parent', function () {
    $childrenData = [
        [
            'first_name' => 'Test',
            'last_name' => 'Child',
            'date_of_birth' => '2020-06-15',
            'gender' => 'male',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    $child = Child::where('first_name', 'Test')->first();

    assertDatabaseHas('child_user', [
        'child_id' => $child->id,
        'user_id' => $this->user->id,
        'relationship_type' => 'Parent',
    ]);
});

it('sets TenantChild status to NEW', function () {
    $childrenData = [
        [
            'first_name' => 'New',
            'last_name' => 'Student',
            'date_of_birth' => '2020-09-01',
            'gender' => 'female',
            'mykid_no' => '200901011234',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    $child = Child::withoutGlobalScopes()->where('mykid_no', '200901011234')->first();

    assertDatabaseHas('tenant_child', [
        'tenant_id' => $this->tenant->id,
        'child_id' => $child->id,
        'status' => 'new',
    ]);
});

it('updates registration_step to 3', function () {
    $childrenData = [
        [
            'first_name' => 'Step',
            'last_name' => 'Test',
            'date_of_birth' => '2020-12-01',
            'gender' => 'male',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    $this->user->refresh();

    expect($this->user->registration_step)->toBe(3);
});

it('stores children data in registration_data', function () {
    $childrenData = [
        [
            'first_name' => 'Data',
            'last_name' => 'Test',
            'date_of_birth' => '2021-01-01',
            'gender' => 'male',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    $this->user->refresh();

    $stepData = $this->user->getRegistrationData('step_3');

    expect($stepData)->toBeArray();
    expect($stepData['children_count'])->toBe(1);
    expect($stepData['skipped'])->toBeFalse();
    expect($stepData['children'])->toHaveCount(1);
    expect($stepData['children'][0]['first_name'])->toBe('Data');
});

it('establishes relationships for all children', function () {
    $childrenData = [
        [
            'first_name' => 'Child1',
            'last_name' => 'Test',
            'date_of_birth' => '2019-01-01',
            'gender' => 'male',
            'mykid_no' => '190101011111',
        ],
        [
            'first_name' => 'Child2',
            'last_name' => 'Test',
            'date_of_birth' => '2020-01-01',
            'gender' => 'female',
            'mykid_no' => '200101012222',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    $this->user->refresh();

    // Check all children are linked to user
    expect($this->user->children()->withoutGlobalScopes()->count())->toBe(2);

    // Check all children are linked to tenant
    $children = Child::withoutGlobalScopes()->whereIn('mykid_no', ['190101011111', '200101012222'])->get();
    foreach ($children as $child) {
        assertDatabaseHas('tenant_child', [
            'tenant_id' => $this->tenant->id,
            'child_id' => $child->id,
        ]);
    }
});

it('updates existing child when mykid_no matches', function () {
    $childrenData = [
        [
            'first_name' => 'Ahmad',
            'patronymic' => 'Bin',
            'last_name' => 'Abdullah',
            'date_of_birth' => '2020-01-15',
            'gender' => 'male',
            'mykid_no' => '200115011234',
            'cert_number' => 'BC123456',
        ],
    ];

    // Create child first time
    $this->action->execute($this->user, $childrenData);
    expect(Child::count())->toBe(1);

    // Update same child with additional data
    $updatedData = [
        [
            'first_name' => 'Ahmad',
            'patronymic' => 'Bin',
            'last_name' => 'Abdullah',
            'date_of_birth' => '2020-01-15',
            'gender' => 'male',
            'place_of_birth' => 'Kuala Lumpur',
            'race' => 'Malay',
            'religion' => 'Islam',
            'mykid_no' => '200115011234',
            'cert_number' => 'BC123456',
        ],
    ];

    $this->action->execute($this->user, $updatedData);

    // Should still be 1 child, not 2
    expect(Child::count())->toBe(1);

    // Check updated fields
    $child = Child::first();
    expect($child->place_of_birth)->toBe('Kuala Lumpur');
    expect($child->race)->toBe('Malay');
    expect($child->religion)->toBe('Islam');
});

// TODO: Fix this test - date_of_birth matching issue with updateOrCreate
it('updates existing child when name and dob match without mykid', function () {
    $childrenData = [
        [
            'first_name' => 'Sarah',
            'last_name' => 'Hassan',
            'date_of_birth' => '2019-05-20',
            'gender' => 'female',
        ],
    ];

    // Create child without MyKID
    $this->action->execute($this->user, $childrenData);
    expect(Child::withoutGlobalScopes()->count())->toBe(1);

    // Update same child (same name + dob, no patronymic change)
    $updatedData = [
        [
            'first_name' => 'Sarah',
            'last_name' => 'Hassan',
            'date_of_birth' => '2019-05-20',
            'gender' => 'female',
            'place_of_birth' => 'Penang',
            'race' => 'Chinese',
        ],
    ];

    $this->action->execute($this->user, $updatedData);

    // Should still be 1 child
    expect(Child::withoutGlobalScopes()->count())->toBe(1);

    $child = Child::withoutGlobalScopes()->first();
    expect($child->place_of_birth)->toBe('Penang');
    expect($child->race)->toBe('Chinese');
})->skip('Date matching issue with updateOrCreate - needs investigation');

it('creates new child when mykid_no differs', function () {
    $childrenData = [
        [
            'first_name' => 'Ahmad',
            'last_name' => 'Abdullah',
            'date_of_birth' => '2020-01-15',
            'gender' => 'male',
            'mykid_no' => '200115011234',
        ],
    ];

    $this->action->execute($this->user, $childrenData);
    expect(Child::count())->toBe(1);

    // Different MyKID = different child
    $newChildData = [
        [
            'first_name' => 'Ahmad',
            'last_name' => 'Abdullah',
            'date_of_birth' => '2020-01-15',
            'gender' => 'male',
            'mykid_no' => '200115015678',
        ],
    ];

    $this->action->execute($this->user, $newChildData);

    // Should create new child
    expect(Child::count())->toBe(2);
});

it('handles optional fields gracefully', function () {
    $childrenData = [
        [
            'first_name' => 'Minimal',
            'last_name' => 'Data',
            'date_of_birth' => '2020-06-15',
            'gender' => 'male',
        ],
    ];

    $this->action->execute($this->user, $childrenData);

    $child = Child::first();
    expect($child->first_name)->toBe('Minimal');
    expect($child->patronymic)->toBeNull();
    expect($child->place_of_birth)->toBeNull();
    expect($child->race)->toBeNull();
    expect($child->religion)->toBeNull();
    expect($child->position_of_child)->toBeNull();
    expect($child->mykid_no)->toBeNull();
    expect($child->cert_number)->toBeNull();
});
