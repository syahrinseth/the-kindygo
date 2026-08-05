<?php

use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RegistrationVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
});

describe('POST /api/v1/auth/register/step-1', function () {
    it('registers a new user with valid data', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'mykad_number' => '900101011234',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'registration' => ['current_step', 'is_complete', 'next_step'],
                    'temporary_token',
                    'tenant',
                    'email_sent_to',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'email_verified' => false,
                    ],
                    'registration' => [
                        'current_step' => 1,
                        'is_complete' => false,
                    ],
                    'email_sent_to' => 'john@example.com',
                ],
            ]);

        // Assert user was created
        $user = User::where('email', 'john@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->name)->toBe('John Doe');
        expect($user->hasVerifiedEmail())->toBeFalse();

        // Assert verification email was sent
        Notification::assertSentTo($user, RegistrationVerificationCode::class);
    });

    it('sends verification code email', function () {
        Notification::fake();

        $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $user = User::where('email', 'jane@example.com')->first();

        Notification::assertSentTo($user, RegistrationVerificationCode::class, function ($notification) {
            // Code should be 6 digits
            expect(strlen($notification->code))->toBe(6);
            expect(is_numeric($notification->code))->toBeTrue();

            return true;
        });
    });

    it('returns temporary token for email verification', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertCreated();

        $temporaryToken = $response->json('data.temporary_token');
        expect($temporaryToken)->not->toBeNull();
        expect(strlen($temporaryToken))->toBe(64); // 32 bytes hex = 64 chars
    });

    it('links user to tenant and centres', function () {
        Notification::fake();

        $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $user = User::where('email', 'test@example.com')->first();

        expect($user->tenants)->toHaveCount(1);
        expect($user->tenants->first()->id)->toBe($this->tenant->id);
        expect($user->centres)->toHaveCount(1);
        expect($user->centres->first()->id)->toBe($this->centre->id);
    });

    it('assigns Parent role to newly registered user', function () {
        Notification::fake();

        $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $user = User::where('email', 'test@example.com')->first();

        expect($user->hasRole('parent'))->toBeTrue();
    });

    it('updates existing unverified user instead of creating duplicate', function () {
        Notification::fake();

        // Create unverified user first
        $existingUser = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'existing@example.com',
            'email_verified_at' => null,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $existingUser->tenants()->attach($this->tenant);

        // Register again with same email
        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'New Name',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertCreated();

        // Should still be only 1 user
        expect(User::where('email', 'existing@example.com')->count())->toBe(1);

        // Name should be updated
        $existingUser->refresh();
        expect($existingUser->name)->toBe('New Name');
    });

    it('rejects registration for already verified email', function () {
        Notification::fake();

        // Create verified user
        $verifiedUser = User::factory()->create([
            'email' => 'verified@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'New Name',
            'email' => 'verified@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error_code' => 'email_already_verified',
            ]);

        Notification::assertNothingSent();
    });

    it('registers device token when provided', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'devicetest@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
            'device_name' => 'iPhone 15 Pro',
            'device_type' => 'ios',
            'device_token' => 'test-fcm-token-12345',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'devicetest@example.com')->first();
        expect($user->deviceTokens)->toHaveCount(1);
        expect($user->deviceTokens->first()->device_token)->toBe('test-fcm-token-12345');
        expect($user->deviceTokens->first()->device_type)->toBe('ios');
    });

    it('succeeds without device token', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'nodevice@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertCreated();
        expect($response->json('device_token_warning'))->toBeNull();
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/auth/register/step-1', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'phone',
                'tenant_slug',
                'centre_ids',
            ]);
    });

    it('validates phone is required', function () {
        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    });

    it('validates tenant slug exists', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => 'non-existent-tenant',
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_slug']);
    });

    it('validates centre ids exist', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [99999],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['centre_ids.0']);
    });

    it('validates email format', function () {
        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password confirmation matches', function () {
        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'DifferentPassword!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('validates device type enum', function () {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
            'device_type' => 'invalid-type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_type']);
    });

    it('requires at least one centre', function () {
        $response = $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['centre_ids']);
    });

    it('stores user profile with mykad and phone', function () {
        Notification::fake();

        $this->postJson('/api/v1/auth/register/step-1', [
            'name' => 'Profile Test',
            'email' => 'profile@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+60123456789',
            'mykad_number' => '900101011234',
            'tenant_slug' => $this->tenant->slug,
            'centre_ids' => [$this->centre->id],
        ]);

        $user = User::where('email', 'profile@example.com')->first();
        expect($user->profile)->not->toBeNull();
        expect($user->profile->nric)->toBe('900101011234');
        expect($user->profile->phone)->toBe('+60123456789');
    });
});
