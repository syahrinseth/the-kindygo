<?php

use App\Constants\TokenAbility;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->tenant2 = Tenant::factory()->create();

    $this->user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->tenant->users()->attach($this->user->id);
    $this->tenant2->users()->attach($this->user->id);
});

describe('GET /api/v1/tenants', function () {
    it('returns list of tenants for authenticated user', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/tenants');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        expect(count($response->json('data')))->toBe(2);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/tenants');

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/tenants/{tenant}/switch', function () {
    it('switches to a valid tenant', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson("/api/v1/tenants/{$this->tenant2->id}/switch");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'tenant',
                    'token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant' => [
                        'id' => $this->tenant2->id,
                    ],
                ],
            ]);
    });

    it('returns error for tenant user does not belong to', function () {
        $otherTenant = Tenant::factory()->create();

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson("/api/v1/tenants/{$otherTenant->id}/switch");

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);
    });

    it('returns 404 for non-existent tenant', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/tenants/99999/switch');

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $response = $this->postJson("/api/v1/tenants/{$this->tenant2->id}/switch");

        $response->assertUnauthorized();
    });
});
