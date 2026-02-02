<?php

use App\Constants\TokenAbility;
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

describe('POST /api/v1/auth/login', function () {
    it('authenticates user with valid credentials', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'device_name' => 'Test Device',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'tenant' => ['id', 'name', 'slug'],
                    'token' => ['access_token', 'token_type', 'expires_at', 'abilities'],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    });

    it('authenticates user with specific tenant', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'tenant_id' => $this->tenant->id,
            'device_name' => 'Test Device',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant' => [
                        'id' => $this->tenant->id,
                    ],
                ],
            ]);
    });

    it('returns error for invalid credentials', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
            'device_name' => 'Test Device',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'error_code' => 'invalid_credentials',
            ]);
    });

    it('returns error for non-existent email', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
            'device_name' => 'Test Device',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
            ]);
    });

    it('returns error for unverified email', function () {
        $unverifiedUser = User::factory()->create([
            'current_tenant_id' => $this->tenant->id,
            'email_verified_at' => null,
        ]);
        $this->tenant->users()->attach($unverifiedUser->id);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $unverifiedUser->email,
            'password' => 'password',
            'device_name' => 'Test Device',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'error_code' => 'email_not_verified',
                'email_verified' => false,
            ]);
    });

    it('returns error for user without tenant access', function () {
        $userWithoutTenant = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $userWithoutTenant->email,
            'password' => 'password',
            'device_name' => 'Test Device',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'error_code' => 'no_tenant_access',
            ]);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('validates email format', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password',
            'device_name' => 'Test Device',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

describe('POST /api/v1/auth/logout', function () {
    it('logs out authenticated user', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out.',
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/auth/logout-all', function () {
    it('logs out user from all devices', function () {
        // Create multiple tokens for user
        $this->user->createToken('Device 1', TokenAbility::parentAbilities());
        $this->user->createToken('Device 2', TokenAbility::parentAbilities());
        $this->user->createToken('Device 3', TokenAbility::parentAbilities());

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/logout-all');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/auth/logout-all');

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/auth/register', function () {
    it('returns 410 Gone for deprecated registration endpoint', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenant_slug' => $this->tenant->slug,
            'device_name' => 'Test Device',
        ]);

        $response->assertStatus(410)
            ->assertJson([
                'success' => false,
                'error_code' => 'endpoint_deprecated',
                'redirect_to' => '/api/v1/auth/register/step-1',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'error_code',
                'redirect_to',
                'documentation',
            ]);
    });

    it('provides helpful deprecation message', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(410);

        $json = $response->json();
        expect($json['message'])->toContain('deprecated');
        expect($json['message'])->toContain('/api/v1/auth/register/step-1');
    });
});

describe('POST /api/v1/auth/verify-email', function () {
    it('returns not implemented for email verification', function () {
        $response = $this->postJson('/api/v1/auth/verify-email', [
            'email' => $this->user->email,
            'code' => '123456',
        ]);

        // Email verification is not yet implemented
        $response->assertStatus(501);
    });
});
