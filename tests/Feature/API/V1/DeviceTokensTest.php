<?php

use App\Constants\TokenAbility;
use App\Models\DeviceToken;
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
});

describe('GET /api/v1/device-tokens', function () {
    it('returns list of device tokens for authenticated user', function () {
        DeviceToken::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/device-tokens');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'device_name',
                        'device_type',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        expect(count($response->json('data')))->toBe(3);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/device-tokens');

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/device-tokens', function () {
    it('registers a new device token', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/device-tokens', [
            'device_token' => 'fcm_token_123456789',
            'device_type' => 'ios',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'device_name',
                    'device_type',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->user->id,
            'device_token' => 'fcm_token_123456789',
            'device_type' => 'ios',
        ]);
    });

    it('updates existing device token', function () {
        $existingToken = DeviceToken::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'device_token' => 'existing_token',
            'device_name' => 'Old Device',
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/device-tokens', [
            'device_token' => 'existing_token',
            'device_type' => 'android',
            'device_name' => 'New Device Name',
        ]);

        $response->assertOk();

        $existingToken->refresh();
        expect($existingToken->device_name)->toBe('New Device Name');
    });

    it('validates required fields', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/device-tokens', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_token', 'device_type']);
    });

    it('validates device type', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/device-tokens', [
            'device_token' => 'some_token',
            'device_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_type']);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/device-tokens', [
            'device_token' => 'fcm_token',
            'device_type' => 'ios',
        ]);

        $response->assertUnauthorized();
    });
});

describe('DELETE /api/v1/device-tokens/{deviceToken}', function () {
    it('deletes a device token', function () {
        $deviceToken = DeviceToken::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->deleteJson("/api/v1/device-tokens/{$deviceToken->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('device_tokens', [
            'id' => $deviceToken->id,
        ]);
    });

    it('returns 404 for non-existent token', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->deleteJson('/api/v1/device-tokens/99999');

        $response->assertNotFound();
    });

    it('cannot delete another user token', function () {
        $otherUser = User::factory()->create();
        $otherToken = DeviceToken::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->deleteJson("/api/v1/device-tokens/{$otherToken->id}");

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $deviceToken = DeviceToken::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->deleteJson("/api/v1/device-tokens/{$deviceToken->id}");

        $response->assertUnauthorized();
    });
});
