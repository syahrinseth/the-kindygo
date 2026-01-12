<?php

use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('centre scope forCurrentTenant filters by user current tenant', function () {
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
        $centre2ForTenant2->id,
    ]);

    // Login as the user
    Auth::login($user);

    // Set current tenant to tenant 1
    $user->update(['current_tenant_id' => $tenant1->id]);
    $user->refresh();

    // Test forCurrentTenant scope - should only return centres for tenant 1
    $centresForTenant1 = Centre::all();
    expect($centresForTenant1)->toHaveCount(2);
    expect($centresForTenant1->pluck('id')->toArray())->toContain($centre1ForTenant1->id);
    expect($centresForTenant1->pluck('id')->toArray())->toContain($centre2ForTenant1->id);
    expect($centresForTenant1->pluck('id')->toArray())->not->toContain($centre1ForTenant2->id);
    expect($centresForTenant1->pluck('id')->toArray())->not->toContain($centre2ForTenant2->id);

    // Switch to tenant 2
    $user->update(['current_tenant_id' => $tenant2->id]);
    $user->refresh();

    // Test forCurrentTenant scope again - should now only return centres for tenant 2
    $centresForTenant2 = Centre::all();
    expect($centresForTenant2)->toHaveCount(2);
    expect($centresForTenant2->pluck('id')->toArray())->toContain($centre1ForTenant2->id);
    expect($centresForTenant2->pluck('id')->toArray())->toContain($centre2ForTenant2->id);
    expect($centresForTenant2->pluck('id')->toArray())->not->toContain($centre1ForTenant1->id);
    expect($centresForTenant2->pluck('id')->toArray())->not->toContain($centre2ForTenant1->id);
});

test('centre scope forCurrentUser filters by user associations and current tenant', function () {
    // Create two users
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create a tenant
    $tenant = Tenant::create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'personal_tenant' => false,
        'email' => 'tenant@example.com',
    ]);

    // Associate both users with the tenant
    $user1->tenants()->attach($tenant->id);
    $user2->tenants()->attach($tenant->id);

    // Set current tenant for both users
    $user1->update(['current_tenant_id' => $tenant->id]);
    $user2->update(['current_tenant_id' => $tenant->id]);

    // Create centres for the tenant
    $centre1 = Centre::create([
        'tenant_id' => $tenant->id,
        'name' => 'Centre 1',
        'slug' => 'centre-1',
    ]);

    $centre2 = Centre::create([
        'tenant_id' => $tenant->id,
        'name' => 'Centre 2',
        'slug' => 'centre-2',
    ]);

    // Associate user1 with centre1 only
    $user1->centres()->attach($centre1->id);

    // Associate user2 with centre2 only
    $user2->centres()->attach($centre2->id);

    // Login as user1
    Auth::login($user1);

    // Test forCurrentUser scope - should only return centre1 (associated with user1)
    $centresForUser1 = Centre::forCurrentUser()->get();
    expect($centresForUser1)->toHaveCount(1);
    expect($centresForUser1->pluck('id')->toArray())->toContain($centre1->id);
    expect($centresForUser1->pluck('id')->toArray())->not->toContain($centre2->id);

    // Login as user2
    Auth::login($user2);

    // Test forCurrentUser scope - should only return centre2 (associated with user2)
    $centresForUser2 = Centre::forCurrentUser()->get();
    expect($centresForUser2)->toHaveCount(1);
    expect($centresForUser2->pluck('id')->toArray())->not->toContain($centre1->id);
    expect($centresForUser2->pluck('id')->toArray())->toContain($centre2->id);
});
