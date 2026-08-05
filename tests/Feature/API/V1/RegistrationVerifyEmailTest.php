<?php

use App\Actions\Auth\VerifyEmailForRegistrationAction;
use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RegistrationVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the Parent role for registration
    Role::create(['name' => 'parent', 'guard_name' => 'web']);

    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Kindergarten',
        'slug' => 'test-kindergarten',
    ]);

    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
    ]);

    $this->verifyEmailAction = app(VerifyEmailForRegistrationAction::class);
});

describe('POST /api/v1/auth/register/verify-email', function () {
    it('verifies email with valid code and returns access token', function () {
        Notification::fake();

        // First, register a user to get a temporary token
        $registerResponse = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $temporaryToken = $registerResponse->json('data.temporary_token');

        // Get the code from the notification
        $user = User::where('email', 'john@example.com')->first();
        Notification::assertSentTo($user, RegistrationVerificationCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        // Verify email with the code
        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => $temporaryToken,
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'email_verified'],
                    'tenant',
                    'token' => ['access_token', 'token_type', 'expires_at'],
                    'registration' => ['current_step', 'next_step', 'is_complete'],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email_verified' => true,
                    ],
                    'registration' => [
                        'current_step' => 1,
                        'next_step' => 2,
                        'is_complete' => false,
                    ],
                ],
            ]);

        // Assert user email is now verified
        $user->refresh();
        expect($user->hasVerifiedEmail())->toBeTrue();
    });

    it('returns error for invalid verification code', function () {
        Notification::fake();

        // Register user
        $registerResponse = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $temporaryToken = $registerResponse->json('data.temporary_token');

        // Try with wrong code
        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => $temporaryToken,
            'code' => '000000',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error_code' => 'invalid_code',
            ]);
    });

    it('returns error for invalid temporary token', function () {
        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => 'invalid-token-12345',
            'code' => '123456',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'error_code' => 'invalid_token',
            ]);
    });

    it('returns error for expired verification code', function () {
        Notification::fake();

        // Create user
        $user = User::factory()->create([
            'email_verified_at' => null,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user->tenants()->attach($this->tenant);

        // Generate code
        $codeData = $this->verifyEmailAction->generateCode($user);

        // Clear cache to simulate expiry
        Cache::flush();

        // Try to verify
        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => $codeData['temporary_token'],
            'code' => $codeData['code'],
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'error_code' => 'invalid_token',
            ]);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/auth/register/verify-email', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['temporary_token', 'code']);
    });

    it('validates code is exactly 6 characters', function () {
        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => 'some-token',
            'code' => '123', // Too short
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => 'some-token',
            'code' => '1234567', // Too long
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('clears verification cache after successful verification', function () {
        Notification::fake();

        // Create user and generate code
        $user = User::factory()->create([
            'email_verified_at' => null,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user->tenants()->attach($this->tenant);

        $codeData = $this->verifyEmailAction->generateCode($user);

        // Verify
        $this->verifyEmailAction->verify($codeData['temporary_token'], $codeData['code']);

        // Try to verify again with same code
        $result = $this->verifyEmailAction->verify($codeData['temporary_token'], $codeData['code']);

        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('invalid_token');
    });

    it('returns full access token on successful verification', function () {
        Notification::fake();

        // Register
        $registerResponse = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Token Test',
            'email' => 'tokentest@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $temporaryToken = $registerResponse->json('data.temporary_token');

        // Get code
        $user = User::where('email', 'tokentest@example.com')->first();
        Notification::assertSentTo($user, RegistrationVerificationCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        // Verify
        $response = $this->postJson('/api/v1/auth/register/verify-email', [
            'temporary_token' => $temporaryToken,
            'code' => $code,
        ]);

        $accessToken = $response->json('data.token.access_token');
        expect($accessToken)->not->toBeNull();

        // Use the token to access protected endpoint
        $protectedResponse = $this->withToken($accessToken)
            ->getJson('/api/v1/auth/register/status');

        $protectedResponse->assertOk();
    });
});
