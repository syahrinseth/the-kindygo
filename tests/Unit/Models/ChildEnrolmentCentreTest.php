<?php

use App\Enums\ChildEnrolmentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Tenant;

it('derives unique centre names from all enrolments', function () {
    $tenant = Tenant::factory()->create();
    $firstCentre = Centre::factory()->forTenant($tenant)->create(['name' => 'First Centre']);
    $secondCentre = Centre::factory()->forTenant($tenant)->create(['name' => 'Second Centre']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $child = Child::factory()->create();
    $child->addToTenant($tenant);

    foreach ([
        [$firstCentre, ChildEnrolmentStatus::ACTIVE],
        [$firstCentre, ChildEnrolmentStatus::CANCELLED],
        [$secondCentre, ChildEnrolmentStatus::PENDING],
    ] as [$centre, $status]) {
        ChildEnrolment::factory()->create([
            'tenant_id' => $tenant->id,
            'child_id' => $child->id,
            'centre_id' => $centre->id,
            'product_id' => $product->id,
            'status' => $status,
        ]);
    }

    $child->load('enrolments.centre');

    expect($child->enrolment_centre_names)->toBe(['First Centre', 'Second Centre']);
});
