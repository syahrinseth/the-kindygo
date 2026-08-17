<?php

use App\Enums\ChildEnrolmentStatus;
use App\Filament\Admin\Resources\Children\ChildResource;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('authorises staff through every enrolment status at their assigned centres', function () {
    Role::firstOrCreate(['name' => 'principal', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $assignedCentre = Centre::factory()->forTenant($tenant)->create();
    $otherCentre = Centre::factory()->forTenant($tenant)->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $principal = User::factory()->create(['current_tenant_id' => $tenant->id]);
    $principal->tenants()->attach($tenant->id);
    $principal->centres()->attach($assignedCentre->id);
    $principal->assignRole('principal');

    $accessibleChild = Child::factory()->create();
    $accessibleChild->addToTenant($tenant);
    ChildEnrolment::factory()->create([
        'tenant_id' => $tenant->id,
        'child_id' => $accessibleChild->id,
        'centre_id' => $assignedCentre->id,
        'product_id' => $product->id,
        'status' => ChildEnrolmentStatus::CANCELLED,
    ]);

    $inaccessibleChild = Child::factory()->create();
    $inaccessibleChild->addToTenant($tenant);
    ChildEnrolment::factory()->create([
        'tenant_id' => $tenant->id,
        'child_id' => $inaccessibleChild->id,
        'centre_id' => $otherCentre->id,
        'product_id' => $product->id,
        'status' => ChildEnrolmentStatus::ACTIVE,
    ]);

    expect($principal->can('view', $accessibleChild))->toBeTrue()
        ->and($principal->can('view', $inaccessibleChild))->toBeFalse();

    $this->actingAs($principal);

    expect(ChildResource::getEloquentQuery()->pluck('children.id')->all())
        ->toBe([$accessibleChild->id]);
});
