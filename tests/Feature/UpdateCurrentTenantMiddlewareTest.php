<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

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
    $user->tenants()->attach($tenant->id);

    // Ensure role exists, give the user a panel role
    Role::firstOrCreate(['name' => 'Admin']);
    $user->assignRole('Admin');

    // Sanity check: user should have tenant attached
    expect($user->tenants()->count())->toBeGreaterThan(0);

    $response = $this->actingAs($user)
        ->get('/admin/centres');

    $response->assertSuccessful();

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

    // Associate user with both tenants - attach them with explicit timestamps
    $baseTime = now();
    $user->tenants()->attach($tenant1->id, ['created_at' => $baseTime, 'updated_at' => $baseTime]);
    $user->tenants()->attach($tenant2->id, ['created_at' => $baseTime, 'updated_at' => $baseTime->copy()->addHour()]);

    // Set initial tenant
    $user->update(['current_tenant_id' => $tenant1->id]);

    // Ensure role exists and assign so user can access panel
    Role::firstOrCreate(['name' => 'Admin']);
    $user->assignRole('Admin');

    // Sanity check: latestTenant should now return tenant2 (it has the newer updated_at)
    $user->refresh();
    $latest = $user->latestTenant()->first();
    expect($latest->id)->toBe($tenant2->id);

    $response = $this->actingAs($user)
        ->get('/admin/centres');

    $response->assertSuccessful();

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

    // Visit root route without authentication
    $response = $this->get('/');

    // Should redirect to login (no error should occur)
    $response->assertRedirect('/login');
});
