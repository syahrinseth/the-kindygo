<?php

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Enums\ProductStatus;
use App\Filament\Admin\Resources\Children\Pages\EditChild;
use App\Filament\Admin\Resources\Children\RelationManagers\EnrolmentsRelationManager;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['profile_completed' => true]);
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->assignRole('admin');
    $this->admin->update(['current_tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('opens the child enrolment create form with tenant centres and complete fields', function (): void {
    $child = Child::factory()->create();
    $child->addToTenant($this->tenant);

    $centre = Centre::factory()->forTenant($this->tenant)->create(['name' => 'Main Centre']);
    $otherTenant = Tenant::factory()->create();
    $otherCentre = Centre::factory()->forTenant($otherTenant)->create(['name' => 'Other Centre']);

    $this->actingAs($this->admin);

    Livewire::test(EnrolmentsRelationManager::class, [
        'ownerRecord' => $child,
        'pageClass' => EditChild::class,
    ])
        ->mountTableAction('create')
        ->assertTableActionMounted('create')
        ->assertFormFieldExists('centre_id', function (Select $field) use ($centre): bool {
            return $field->getOptions() === [$centre->id => $centre->name];
        })
        ->assertFormFieldExists('product_id')
        ->assertFormFieldExists('status')
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('billed_every')
        ->assertFormFieldExists('date_start')
        ->assertFormFieldExists('date_end')
        ->assertFormFieldExists('additional_products');

    expect(Centre::query()->pluck('name', 'id')->all())
        ->toHaveKey($centre->id)
        ->not->toHaveKey($otherCentre->id);
});

it('shows only active products assigned to the selected centre', function (): void {
    $centre = Centre::factory()->forTenant($this->tenant)->create();
    $assignedProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => ProductStatus::ACTIVE->value,
    ]);
    $assignedProduct->centres()->attach($centre->id);

    $otherCentre = Centre::factory()->forTenant($this->tenant)->create();
    $otherCentreProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => ProductStatus::ACTIVE->value,
    ]);
    $otherCentreProduct->centres()->attach($otherCentre->id);
    $inactiveProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => ProductStatus::INACTIVE->value,
    ]);

    $this->actingAs($this->admin);

    $child = Child::factory()->create();
    $child->addToTenant($this->tenant);

    Livewire::test(EnrolmentsRelationManager::class, [
        'ownerRecord' => $child,
        'pageClass' => EditChild::class,
    ])
        ->mountTableAction('create')
        ->fillForm(['centre_id' => $centre->id])
        ->assertFormFieldExists('product_id', function (Select $field) use ($assignedProduct): bool {
            return $field->getOptions() === [$assignedProduct->id => $assignedProduct->name];
        });

    expect($otherCentreProduct->id)->not->toBe($assignedProduct->id)
        ->and($inactiveProduct->id)->not->toBe($assignedProduct->id);
});

it('creates an enrolment from the child relation manager with the selected values', function (): void {
    $child = Child::factory()->create();
    $child->addToTenant($this->tenant);

    $centre = Centre::factory()->forTenant($this->tenant)->create();
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => ProductStatus::ACTIVE->value,
    ]);
    $product->centres()->attach($centre->id);

    $this->actingAs($this->admin);

    Livewire::test(EnrolmentsRelationManager::class, [
        'ownerRecord' => $child,
        'pageClass' => EditChild::class,
    ])->callTableAction('create', data: [
        'centre_id' => $centre->id,
        'product_id' => $product->id,
        'status' => ChildEnrolmentStatus::PENDING->value,
        'type' => ChildEnrolmentType::FULL_TIME->value,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY->value,
        'date_start' => '2026-08-17 09:00:00',
        'date_end' => null,
    ])->assertHasNoFormErrors();

    $this->assertDatabaseHas('child_enrolments', [
        'tenant_id' => $this->tenant->id,
        'child_id' => $child->id,
        'centre_id' => $centre->id,
        'product_id' => $product->id,
        'status' => ChildEnrolmentStatus::PENDING->value,
        'type' => ChildEnrolmentType::FULL_TIME->value,
        'billed_every' => ChildEnrolmentBilledEvery::MONTHLY->value,
    ]);
});

it('switches trial enrolments to one-time billing', function (): void {
    $child = Child::factory()->create();
    $child->addToTenant($this->tenant);

    $this->actingAs($this->admin);

    Livewire::test(EnrolmentsRelationManager::class, [
        'ownerRecord' => $child,
        'pageClass' => EditChild::class,
    ])
        ->mountTableAction('create')
        ->fillForm([
            'type' => ChildEnrolmentType::TRIAL->value,
        ])
        ->assertTableActionDataSet([
            'type' => ChildEnrolmentType::TRIAL->value,
            'billed_every' => ChildEnrolmentBilledEvery::ONE_TIME->value,
        ]);
});
