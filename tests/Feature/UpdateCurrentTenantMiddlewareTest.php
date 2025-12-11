<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user current tenant id is updated when accessing tenant route', function () {
    /** @var Tests\TestCase $this */

    // Create a user
    /** @var User $user */
    $user = User::factory()->create([
        'current_tenant_id' => null,
    ]);

    // Create a tenant manually
    $tenant = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Test Company',
        'slug' => 'test-company',
        'personal_tenant' => false,
        'email' => 'test@example.com',
    ]);

    // Associate user with tenant
    $user->tenants()->attach($tenant);

    // Act as the user and visit a tenant route
    $response = $this->actingAs($user)
        ->get("/app/{$tenant->slug}");

    // Assert the user's current_tenant_id was updated
    $user->refresh();
    expect($user->current_tenant_id)->toBe($tenant->id);
});

test('user current tenant id is updated when switching between tenants', function () {
    /** @var Tests\TestCase $this */

    // Create a user
    /** @var User $user */
    $user = User::factory()->create();

    // Create two tenants manually
    $tenant1 = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Company One',
        'slug' => 'company-one',
        'personal_tenant' => false,
        'email' => 'one@example.com',
    ]);

    $tenant2 = Tenant::create([
        'user_id' => $user->id,
        'name' => 'Company Two',
        'slug' => 'company-two',
        'personal_tenant' => false,
        'email' => 'two@example.com',
    ]);

    // Associate user with both tenants
    $user->tenants()->attach([$tenant1->id, $tenant2->id]);

    // Set initial tenant
    $user->update(['current_tenant_id' => $tenant1->id]);

    // Switch to second tenant
    $response = $this->actingAs($user)
        ->get("/app/{$tenant2->slug}");

    // Assert the user's current_tenant_id was updated to the new tenant
    $user->refresh();
    expect($user->current_tenant_id)->toBe($tenant2->id);
});

test('middleware does not run for unauthenticated users', function () {
    /** @var Tests\TestCase $this */

    // Create a tenant manually
    $tenant = Tenant::create([
        'name' => 'Test Company',
        'slug' => 'test-company',
        'personal_tenant' => false,
        'email' => 'test@example.com',
    ]);

    // Visit tenant route without authentication
    $response = $this->get("/app/{$tenant->slug}");

    // Should redirect to login (no error should occur)
    $response->assertRedirect('/login');
});
