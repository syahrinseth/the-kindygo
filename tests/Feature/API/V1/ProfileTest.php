<?php

use App\Constants\TokenAbility;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->tenant->users()->attach($this->user->id);
});

describe('GET /api/v1/profile', function () {
    it('returns authenticated user profile', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ]);
    });

    it('includes profile and address when present', function () {
        $this->user->profile()->create([
            'phone' => '0123456789',
            'nric' => '900101-01-1234',
        ]);

        $this->user->userAddress()->create([
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state_code' => '10', // Selangor
            'postal_code' => '40000',
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'profile',
                    'address',
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/profile');

        $response->assertUnauthorized();
    });
});

describe('PUT /api/v1/profile', function () {
    it('updates user name', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => [
                    'name' => 'Updated Name',
                ],
            ]);

        $this->user->refresh();
        expect($this->user->name)->toBe('Updated Name');
    });

    it('updates user profile details', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson('/api/v1/profile', [
            'profile' => [
                'phone' => '0199876543',
                'nric' => '900101-01-5678',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->user->refresh();
        expect($this->user->profile)->not->toBeNull();
        expect($this->user->profile->phone)->toBe('0199876543');
        expect($this->user->profile->nric)->toBe('900101-01-5678');
    });

    it('updates user address', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson('/api/v1/profile', [
            'address' => [
                'address' => '456 New Street',
                'address_2' => 'Apartment 5B',
                'city' => 'Kuala Lumpur',
                'state_code' => '14', // WP Kuala Lumpur
                'postal_code' => '50000',
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->user->refresh();
        expect($this->user->userAddress)->not->toBeNull();
        expect($this->user->userAddress->address)->toBe('456 New Street');
        expect($this->user->userAddress->city)->toBe('Kuala Lumpur');
    });

    it('requires authentication', function () {
        $response = $this->putJson('/api/v1/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertUnauthorized();
    });

    it('validates name max length', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson('/api/v1/profile', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('POST /api/v1/profile/photo', function () {
    it('uploads profile photo', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $photo = UploadedFile::fake()->image('profile.jpg', 500, 500);

        $response = $this->postJson('/api/v1/profile/photo', [
            'photo' => $photo,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['photo_url'],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo uploaded successfully.',
            ]);

        expect($this->user->hasMedia('photo'))->toBeTrue();
    });

    it('validates photo is required', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/profile/photo', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    });

    it('validates photo must be an image', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson('/api/v1/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    });

    it('validates photo max size is 5MB', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        // Create file larger than 5MB
        $photo = UploadedFile::fake()->image('large.jpg')->size(6000);

        $response = $this->postJson('/api/v1/profile/photo', [
            'photo' => $photo,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    });

    it('requires authentication', function () {
        $photo = UploadedFile::fake()->image('profile.jpg');

        $response = $this->postJson('/api/v1/profile/photo', [
            'photo' => $photo,
        ]);

        $response->assertUnauthorized();
    });
});

describe('DELETE /api/v1/profile/photo', function () {
    it('deletes profile photo', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        // First upload a photo
        $photo = UploadedFile::fake()->image('profile.jpg');
        $this->user->addMedia($photo)->toMediaCollection('photo');
        expect($this->user->hasMedia('photo'))->toBeTrue();

        $response = $this->deleteJson('/api/v1/profile/photo');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo deleted successfully.',
            ]);

        $this->user->refresh();
        expect($this->user->hasMedia('photo'))->toBeFalse();
    });

    it('succeeds even if no photo exists', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->deleteJson('/api/v1/profile/photo');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    });

    it('requires authentication', function () {
        $response = $this->deleteJson('/api/v1/profile/photo');

        $response->assertUnauthorized();
    });
});
