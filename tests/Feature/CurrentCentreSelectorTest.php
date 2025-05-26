<?php

use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('user can only see centres for current tenant', function () {
    // Create a user
    $user = User::factory()->create();

    // Create two tenants
    $tenant1 = Tenant::create([
        'name' => 'Tenant One',
        'slug' => 'tenant-one',
        'personal_tenant' => false,
        'email' => 'tenant1@example.com',
    ]);

    $tenant2 = Tenant::create([
        'name' => 'Tenant Two',
        'slug' => 'tenant-two',
        'personal_tenant' => false,
        'email' => 'tenant2@example.com',
    ]);

    // Associate user with both tenants
    $user->tenants()->attach([$tenant1->id, $tenant2->id]);

    // Create centres for tenant 1
    $centre1ForTenant1 = Centre::create([
        'tenant_id' => $tenant1->id,
        'name' => 'Centre 1 for Tenant 1',
        'slug' => 'centre-1-tenant-1',
    ]);

    $centre2ForTenant1 = Centre::create([
        'tenant_id' => $tenant1->id,
        'name' => 'Centre 2 for Tenant 1',
        'slug' => 'centre-2-tenant-1',
    ]);

    // Create centres for tenant 2
    $centre1ForTenant2 = Centre::create([
        'tenant_id' => $tenant2->id,
        'name' => 'Centre 1 for Tenant 2',
        'slug' => 'centre-1-tenant-2',
    ]);

    $centre2ForTenant2 = Centre::create([
        'tenant_id' => $tenant2->id,
        'name' => 'Centre 2 for Tenant 2',
        'slug' => 'centre-2-tenant-2',
    ]);

    // Associate user with all centres
    $user->centres()->attach([
        $centre1ForTenant1->id,
        $centre2ForTenant1->id,
        $centre1ForTenant2->id,
        $centre2ForTenant2->id
    ]);

    // Login as the user and set current tenant to tenant 1
    Auth::login($user);
    $user->update(['current_tenant_id' => $tenant1->id]);
    $user->refresh();

    // Check centres for current tenant (Tenant 1)
    $centresForTenant1 = Centre::forCurrentUser()->get();
    expect($centresForTenant1)->toHaveCount(2);
    expect($centresForTenant1->pluck('id')->toArray())->toContain($centre1ForTenant1->id);
    expect($centresForTenant1->pluck('id')->toArray())->toContain($centre2ForTenant1->id);
    expect($centresForTenant1->pluck('id')->toArray())->not->toContain($centre1ForTenant2->id);
    expect($centresForTenant1->pluck('id')->toArray())->not->toContain($centre2ForTenant2->id);

    // Switch to tenant 2
    $user->update(['current_tenant_id' => $tenant2->id]);
    $user->refresh();

    // Check centres for current tenant (Tenant 2)
    $centresForTenant2 = Centre::forCurrentUser()->get();
    expect($centresForTenant2)->toHaveCount(2);
    expect($centresForTenant2->pluck('id')->toArray())->toContain($centre1ForTenant2->id);
    expect($centresForTenant2->pluck('id')->toArray())->toContain($centre2ForTenant2->id);
    expect($centresForTenant2->pluck('id')->toArray())->not->toContain($centre1ForTenant1->id);
    expect($centresForTenant2->pluck('id')->toArray())->not->toContain($centre2ForTenant1->id);
});

test('widget canView method returns correct visibility based on tenant', function () {
    // Create a user
    $user = User::factory()->create();

    // Create two tenants
    $tenant1 = Tenant::create([
        'name' => 'Tenant One',
        'slug' => 'tenant-one',
        'personal_tenant' => false,
        'email' => 'tenant1@example.com',
    ]);

    $tenant2 = Tenant::create([
        'name' => 'Tenant Two',
        'slug' => 'tenant-two',
        'personal_tenant' => false,
        'email' => 'tenant2@example.com',
    ]);

    // Associate user with both tenants
    $user->tenants()->attach([$tenant1->id, $tenant2->id]);

    // Create centres for tenant 1 only
    $centre1 = Centre::create([
        'tenant_id' => $tenant1->id,
        'name' => 'Centre 1',
        'slug' => 'centre-1',
    ]);

    // Associate user with the centre
    $user->centres()->attach($centre1->id);

    // Login as the user and set current tenant to tenant 1
    Auth::login($user);
    $user->update(['current_tenant_id' => $tenant1->id]);
    $user->refresh();

    // User has centres in tenant 1, should return true
    expect(Centre::forCurrentUser()->exists())->toBeTrue();

    // Switch to tenant 2 (which has no centres)
    $user->update(['current_tenant_id' => $tenant2->id]);
    $user->refresh();

    // User has no centres in tenant 2, should return false
    expect(Centre::forCurrentUser()->exists())->toBeFalse();
});