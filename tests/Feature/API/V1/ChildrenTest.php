<?php

use App\Constants\TokenAbility;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->tenant->users()->attach($this->user->id);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);
    $this->child->users()->attach($this->user->id);
});

describe('GET /api/v1/children', function () {
    it('returns list of children for authenticated user', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/children');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonMissingPath('data.0.centres');
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/children');

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/children/{child}', function () {
    it('returns child details', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/children/{$this->child->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->child->id,
                ],
            ])
            ->assertJsonMissingPath('data.centres');
    });

    it('returns 404 for non-existent child', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/children/99999');

        $response->assertNotFound();
    });

    it('returns 403 for child not belonging to user', function () {
        $otherChild = Child::factory()->create();
        $otherChild->tenants()->attach($this->tenant->id);
        // Not attached to user

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/children/{$otherChild->id}");

        $response->assertForbidden();
    });

    it('requires authentication', function () {
        $response = $this->getJson("/api/v1/children/{$this->child->id}");

        $response->assertUnauthorized();
    });
});
