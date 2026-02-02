<?php

use App\Constants\TokenAbility;
use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');

    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Kindergarten',
        'slug' => 'test-kindergarten',
    ]);

    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
    ]);

    // Create verified user at step 1
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 1,
        'profile_completed' => false,
    ]);
    $this->user->tenants()->attach($this->tenant);
    $this->user->centres()->attach($this->centre);
});

describe('POST /api/v1/auth/register/step-2', function () {
    it('updates parent details with valid data', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'occupation' => 'Software Engineer',
            'profile_photo' => UploadedFile::fake()->image('profile.jpg', 800, 800),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg', 1000, 600),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg', 1200, 800),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'registration' => ['current_step', 'is_complete', 'next_step'],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'registration' => [
                        'current_step' => 2,
                        'is_complete' => false,
                    ],
                    'next_step' => 3,
                ],
            ]);

        // Assert user data was updated
        $this->user->refresh();
        expect($this->user->userAddress)->not->toBeNull();
        expect($this->user->userAddress->address)->toBe('123 Main Street');
        expect($this->user->userAddress->postal_code)->toBe('50000');
        expect($this->user->userAddress->city)->toBe('Kuala Lumpur');
    });

    it('uploads profile photo successfully', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $this->user->refresh();
        expect($this->user->getFirstMedia('photo'))->not->toBeNull();
    });

    it('uploads mykad image successfully', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $this->user->refresh();
        expect($this->user->getFirstMedia('mykad'))->not->toBeNull();
    });

    it('uploads immunization card successfully', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $this->user->refresh();
        expect($this->user->getFirstMedia('immunization'))->not->toBeNull();
    });

    it('stores office information when provided', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'office_address' => '456 Office Tower',
            'office_postal_code' => '50100',
            'office_city' => 'Kuala Lumpur',
            'office_state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $this->user->refresh();
        expect($this->user->officeInfo)->not->toBeNull();
        expect($this->user->officeInfo->office_address)->toBe('456 Office Tower');
    });

    it('rejects unverified email user', function () {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $unverifiedUser->tenants()->attach($this->tenant);

        Sanctum::actingAs($unverifiedUser, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'error_code' => 'email_not_verified',
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

        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error_code' => 'registration_complete',
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-2', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'address',
                'postal_code',
                'city',
                'state',
                'profile_photo',
                'mykad_image',
                'immunization_card',
            ]);
    });

    it('validates profile photo is an image', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->create('document.txt', 100),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo']);
    });

    it('validates file size limits', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg')->size(6000), // 6MB
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['profile_photo']);
    });

    it('accepts PDF for mykad image', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        // Create a valid PDF file with actual content
        $pdfContent = '%PDF-1.4 test content';
        $pdfFile = UploadedFile::fake()->createWithContent('mykad.pdf', $pdfContent);

        $response = $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => $pdfFile,
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $response->assertOk();
    });

    it('updates registration step to 2', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-2', [
            'address' => '123 Main Street',
            'postal_code' => '50000',
            'city' => 'Kuala Lumpur',
            'state' => '14', // WP Kuala Lumpur
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
            'mykad_image' => UploadedFile::fake()->image('mykad.jpg'),
            'immunization_card' => UploadedFile::fake()->image('immunization.jpg'),
        ]);

        $this->user->refresh();
        expect($this->user->registration_step)->toBe(2);
    });
});
