<?php

use App\Constants\TokenAbility;
use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Kindergarten',
        'slug' => 'test-kindergarten',
    ]);

    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
    ]);

    // Create verified user at step 3 (completed step 3)
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 3,
        'profile_completed' => false,
    ]);
    $this->user->tenants()->attach($this->tenant);
    $this->user->centres()->attach($this->centre);
});

describe('POST /api/v1/auth/register/step-4', function () {
    it('completes registration successfully', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'tenant',
                    'registration' => ['current_step', 'is_complete'],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'registration' => [
                        'current_step' => 4,
                        'is_complete' => true,
                    ],
                ],
            ]);

        // Assert user profile is marked as complete
        $this->user->refresh();
        expect($this->user->profile_completed)->toBeTrue();
        expect($this->user->registration_step)->toBe(4);
    });

    it('requires tnc_accepted to be true', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => false,
            'undertaking_accepted' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tnc_accepted']);
    });

    it('requires undertaking_accepted to be true', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['undertaking_accepted']);
    });

    it('requires tnc_accepted field', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'undertaking_accepted' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tnc_accepted']);
    });

    it('requires undertaking_accepted field', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['undertaking_accepted']);
    });

    it('validates both agreements are required', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tnc_accepted', 'undertaking_accepted']);
    });

    it('marks profile_completed to true', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        expect($this->user->profile_completed)->toBeFalse();

        $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $this->user->refresh();
        expect($this->user->profile_completed)->toBeTrue();
    });

    it('sets registration_step to 4', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $this->user->refresh();
        expect($this->user->registration_step)->toBe(4);
    });

    it('returns welcome message with app name', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Registration completed successfully! Welcome to '.config('app.name').'.',
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertUnauthorized();
    });

    it('rejects unverified email user', function () {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
            'current_tenant_id' => $this->tenant->id,
            'registration_step' => 3,
        ]);
        $unverifiedUser->tenants()->attach($this->tenant);

        Sanctum::actingAs($unverifiedUser, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'error_code' => 'email_not_verified',
            ]);
    });

    it('rejects if step 3 not completed', function () {
        $step2User = User::factory()->create([
            'email_verified_at' => now(),
            'current_tenant_id' => $this->tenant->id,
            'registration_step' => 2,
            'profile_completed' => false,
        ]);
        $step2User->tenants()->attach($this->tenant);

        Sanctum::actingAs($step2User, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error_code' => 'step_not_complete',
            ]);
    });

    it('rejects already completed registration', function () {
        $completedUser = User::factory()->create([
            'email_verified_at' => now(),
            'current_tenant_id' => $this->tenant->id,
            'registration_step' => 4,
            'profile_completed' => true,
        ]);
        $completedUser->tenants()->attach($this->tenant);

        Sanctum::actingAs($completedUser, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error_code' => 'registration_complete',
            ]);
    });

    it('includes user and tenant in response', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'tenant' => ['id', 'name', 'slug'],
                ],
            ]);
    });

    it('clears registration token after completion', function () {
        // Set a registration token first
        $this->user->registration_token = 'test-token-123';
        $this->user->save();

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-4', [
            'tnc_accepted' => true,
            'undertaking_accepted' => true,
        ]);

        $this->user->refresh();
        expect($this->user->registration_token)->toBeNull();
    });
});

describe('GET /api/v1/auth/register/status', function () {
    it('returns current registration progress', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/auth/register/status');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'registration' => ['current_step', 'is_complete'],
                    'tenant',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'registration' => [
                        'current_step' => 3,
                        'is_complete' => false,
                    ],
                ],
            ]);
    });

    it('shows complete status for finished registration', function () {
        $completedUser = User::factory()->create([
            'email_verified_at' => now(),
            'current_tenant_id' => $this->tenant->id,
            'registration_step' => 4,
            'profile_completed' => true,
        ]);
        $completedUser->tenants()->attach($this->tenant);

        Sanctum::actingAs($completedUser, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/auth/register/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'registration' => [
                        'current_step' => 4,
                        'is_complete' => true,
                    ],
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/auth/register/status');

        $response->assertUnauthorized();
    });
});
