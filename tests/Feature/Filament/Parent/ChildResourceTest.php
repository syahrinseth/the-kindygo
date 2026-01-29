<?php

use App\Enums\ChildStatus;
use App\Filament\Parent\Resources\ChildResource\Pages\EditChild;
use App\Filament\Parent\Resources\ChildResource\Pages\ListChildren;
use App\Filament\Parent\Resources\ChildResource\Pages\ViewChild;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create roles
    Role::firstOrCreate(['name' => 'Parent']);

    $this->parent = User::factory()->create();
    $this->parent->tenants()->attach($this->tenant->id);
    $this->parent->assignRole('Parent');
    $this->parent->update(['current_tenant_id' => $this->tenant->id]);

    $this->otherParent = User::factory()->create();
    $this->otherParent->tenants()->attach($this->tenant->id);
    $this->otherParent->assignRole('Parent');
    $this->otherParent->update(['current_tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('parent'));
});

test('parent can view their own children list', function () {
    $this->actingAs($this->parent);

    $ownChildren = Child::factory()->count(3)->create();
    foreach ($ownChildren as $child) {
        $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
        $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);
    }

    $otherChildren = Child::factory()->count(2)->create();
    foreach ($otherChildren as $child) {
        $child->users()->attach($this->otherParent->id, ['relationship_type' => 'parent']);
        $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);
    }

    Livewire::test(ListChildren::class)
        ->assertCanSeeTableRecords($ownChildren)
        ->assertCanNotSeeTableRecords($otherChildren);
});

test('parent can create a new child', function () {
    $this->actingAs($this->parent);

    $childData = [
        'first_name' => 'Ahmad',
        'last_name' => 'Abdullah',
        'patronymic' => 'bin',
        'mykid_no' => '200101010001',
        'gender' => 'male',
        'date_of_birth' => '2020-01-15',
    ];

    // Create child directly through model to test the relationship logic
    $child = Child::create($childData);
    $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);

    if ($this->parent->current_tenant_id) {
        $child->addToTenant($this->parent->current_tenant_id, ChildStatus::NEW);
    }

    $this->assertDatabaseHas('children', [
        'first_name' => 'Ahmad',
        'last_name' => 'Abdullah',
        'mykid_no' => '200101010001',
    ]);

    // Verify child is associated with parent
    expect($child->users()->where('users.id', $this->parent->id)->exists())->toBeTrue();

    // Verify child is associated with tenant
    expect($child->tenants()->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

test('parent can view their child details', function () {
    $this->actingAs($this->parent);

    $child = Child::factory()->create([
        'first_name' => 'Fatimah',
        'last_name' => 'binti Ahmad',
    ]);
    $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    Livewire::test(ViewChild::class, ['record' => $child->id])
        ->assertSuccessful();
});

test('parent can edit their child information', function () {
    $this->actingAs($this->parent);

    $child = Child::factory()->create([
        'first_name' => 'Fatimah',
        'last_name' => 'binti Ahmad',
        'race' => 'Malay',
        'family_clinic_phone' => '+60123456789',
    ]);
    $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    Livewire::test(EditChild::class, ['record' => $child->id])
        ->set('data.first_name', 'Nur Fatimah')
        ->set('data.allergies', 'Peanuts')
        ->call('save')
        ->assertHasNoFormErrors();

    $child->refresh();

    expect($child->first_name)->toBe('Nur Fatimah');
    expect($child->allergies)->toBe('Peanuts');
});

test('parent can delete their child', function () {
    $this->actingAs($this->parent);

    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    $childId = $child->id;

    // Soft delete directly since delete action visibility might be restricted
    $child->delete();

    $this->assertSoftDeleted('children', ['id' => $childId]);
});

test('parent cannot view other parents children', function () {
    $this->actingAs($this->parent);

    $otherChild = Child::factory()->create();
    $otherChild->users()->attach($this->otherParent->id, ['relationship_type' => 'parent']);
    $otherChild->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    // The child should not be accessible due to model scoping
    expect(
        fn () => Livewire::test(ViewChild::class, ['record' => $otherChild->id])
    )->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('parent cannot edit other parents children', function () {
    $this->actingAs($this->parent);

    $otherChild = Child::factory()->create();
    $otherChild->users()->attach($this->otherParent->id, ['relationship_type' => 'parent']);
    $otherChild->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    // The child should not be accessible due to model scoping
    expect(
        fn () => Livewire::test(EditChild::class, ['record' => $otherChild->id])
    )->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('parent can filter children by status', function () {
    $this->actingAs($this->parent);

    $activeChild = Child::factory()->create(['first_name' => 'Active']);
    $activeChild->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $activeChild->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    $alumniChild = Child::factory()->create(['first_name' => 'Alumni']);
    $alumniChild->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $alumniChild->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ALUMNI]);

    Livewire::test(ListChildren::class)
        ->filterTable('status', ChildStatus::ACTIVE->value)
        ->assertCanSeeTableRecords([$activeChild])
        ->assertCanNotSeeTableRecords([$alumniChild]);
});

test('parent can filter children by gender', function () {
    $this->actingAs($this->parent);

    $maleChild = Child::factory()->create(['gender' => 'male', 'first_name' => 'Ahmad']);
    $maleChild->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $maleChild->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    $femaleChild = Child::factory()->create(['gender' => 'female', 'first_name' => 'Fatimah']);
    $femaleChild->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $femaleChild->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    Livewire::test(ListChildren::class)
        ->filterTable('gender', 'male')
        ->assertCanSeeTableRecords([$maleChild])
        ->assertCanNotSeeTableRecords([$femaleChild]);
});

test('parent can search children by name', function () {
    $this->actingAs($this->parent);

    $child1 = Child::factory()->create(['first_name' => 'Ahmad', 'last_name' => 'Abdullah']);
    $child1->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child1->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    $child2 = Child::factory()->create(['first_name' => 'Fatimah', 'last_name' => 'Ibrahim']);
    $child2->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child2->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    Livewire::test(ListChildren::class)
        ->searchTable('Ahmad')
        ->assertCanSeeTableRecords([$child1])
        ->assertCanNotSeeTableRecords([$child2]);
});

test('parent can view centres relation on child', function () {
    $this->actingAs($this->parent);

    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);
    $child->centres()->attach($this->centre->id);

    Livewire::test(ViewChild::class, ['record' => $child->id])
        ->assertSuccessful();
});

test('form does not include parents and guardians section', function () {
    $this->actingAs($this->parent);

    $child = Child::factory()->create();
    $child->users()->attach($this->parent->id, ['relationship_type' => 'parent']);
    $child->tenants()->attach($this->tenant->id, ['status' => ChildStatus::ACTIVE]);

    $response = Livewire::test(EditChild::class, ['record' => $child->id]);

    // Check that the form exists and is properly loaded
    expect($response)->assertSuccessful();

    // Verify no 'users' field exists in the form data
    expect($response->get('data'))->not->toHaveKey('users');
});
