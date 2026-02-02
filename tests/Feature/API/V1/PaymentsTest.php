<?php

use App\Constants\TokenAbility;
use App\Models\Centre;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->tenant->users()->attach($this->user->id);
});

describe('GET /api/v1/payments', function () {
    it('returns list of payments for authenticated user', function () {
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 3,
                ],
            ]);
    });

    it('only returns payments belonging to authenticated user', function () {
        Payment::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
        // Create payment for another user
        $otherUser = User::factory()->create();
        Payment::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/payments');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 1,
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/payments');

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/payments/{payment}', function () {
    it('returns payment details', function () {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $payment->id,
                ],
            ]);
    });

    it('returns 404 for non-existent payment', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/payments/99999');

        $response->assertNotFound();
    });

    it('returns 404 for payment not belonging to user', function () {
        $otherUser = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/payments/{payment}/confirm', function () {
    it('returns payment confirmation status', function () {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson("/api/v1/payments/{$payment->id}/confirm");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'is_completed',
                    'payment',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    });

    it('returns 404 for payment not belonging to user', function () {
        $otherUser = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson("/api/v1/payments/{$payment->id}/confirm");

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/confirm");

        $response->assertUnauthorized();
    });
});
