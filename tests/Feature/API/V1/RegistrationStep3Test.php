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
    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Kindergarten',
        'slug' => 'test-kindergarten',
    ]);

    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Main Campus',
    ]);

    // Create verified user at step 2 (completed step 2)
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'current_tenant_id' => $this->tenant->id,
        'registration_step' => 2,
        'profile_completed' => false,
    ]);
    $this->user->tenants()->attach($this->tenant);
    $this->user->centres()->attach($this->centre);
});

describe('POST /api/v1/auth/register/step-3', function () {
    it('adds children successfully', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Bin Ali',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                    'place_of_birth' => 'Kuala Lumpur',
                    'race' => 'Malay',
                    'religion' => 'Islam',
                    'mykid_no' => '200515011234',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'registration' => ['current_step', 'is_complete'],
                    'next_step',
                    'children_added',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'registration' => [
                        'current_step' => 3,
                        'is_complete' => false,
                    ],
                    'next_step' => 4,
                    'children_added' => 1,
                ],
            ]);

        // Assert child was created
        $child = Child::where('first_name', 'Ahmad')->first();
        expect($child)->not->toBeNull()
            ->and($child->mykid_no)->toBe('200515-01-1234')
            ->and($this->user->fresh()->getRegistrationData('step_3.children.0.mykid_no'))->toBe('200515-01-1234');

        // Assert relationship was established
        $this->user->refresh();
        expect($this->user->children()->count())->toBe(1);
    });

    it('adds multiple children successfully', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Bin Ali',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                ],
                [
                    'first_name' => 'Fatimah',
                    'last_name' => 'Binti Ali',
                    'date_of_birth' => '2018-03-20',
                    'gender' => 'female',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'children_added' => 2,
                ],
            ]);

        expect(Child::count())->toBe(2);
        $this->user->refresh();
        expect($this->user->children()->count())->toBe(2);
    });

    it('does not duplicate children without MyKid numbers when step three is retried', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $payload = [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Bin Ali',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                ],
            ],
        ];

        $this->postJson('/api/v1/auth/register/step-3', $payload)->assertOk();
        $this->postJson('/api/v1/auth/register/step-3', $payload)->assertOk();

        expect(Child::count())->toBe(1)
            ->and($this->user->fresh()->children()->count())->toBe(1);
    });

    it('skips children with empty array', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Children step skipped.',
                'data' => [
                    'registration' => [
                        'current_step' => 3,
                        'is_complete' => false,
                    ],
                    'next_step' => 4,
                    'children_added' => 0,
                ],
            ]);

        expect(Child::count())->toBe(0);
    });

    it('skips children when field is omitted', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', []);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Children step skipped.',
                'data' => [
                    'children_added' => 0,
                ],
            ]);
    });

    it('validates child first_name is required', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['children.0.first_name']);
    });

    it('validates child date_of_birth is required', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'gender' => 'male',
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['children.0.date_of_birth']);
    });

    it('validates child gender is required', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'date_of_birth' => '2020-05-15',
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['children.0.gender']);
    });

    it('validates gender must be male or female', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'other',
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['children.0.gender']);
    });

    it('validates date_of_birth must be in the past', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'date_of_birth' => now()->addYear()->format('Y-m-d'),
                    'gender' => 'male',
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['children.0.date_of_birth']);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [],
        ]);

        $response->assertUnauthorized();
    });

    it('rejects unverified email user', function () {
        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
            'current_tenant_id' => $this->tenant->id,
            'registration_step' => 2,
        ]);
        $unverifiedUser->tenants()->attach($this->tenant);

        Sanctum::actingAs($unverifiedUser, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [],
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'error_code' => 'email_not_verified',
            ]);
    });

    it('rejects if step 2 not completed', function () {
        $step1User = User::factory()->create([
            'email_verified_at' => now(),
            'current_tenant_id' => $this->tenant->id,
            'registration_step' => 1,
            'profile_completed' => false,
        ]);
        $step1User->tenants()->attach($this->tenant);

        Sanctum::actingAs($step1User, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [],
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

        $response = $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error_code' => 'registration_complete',
            ]);
    });

    it('updates registration step to 3', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Bin Ali',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                ],
            ],
        ]);

        $this->user->refresh();
        expect($this->user->registration_step)->toBe(3);
    });

    it('links child to tenant via TenantChild', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Bin Ali',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                ],
            ],
        ]);

        $child = Child::where('first_name', 'Ahmad')->first();
        expect($child->tenants()->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
    });

    it('establishes parent relationship with child', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->postJson('/api/v1/auth/register/step-3', [
            'children' => [
                [
                    'first_name' => 'Ahmad',
                    'last_name' => 'Bin Ali',
                    'date_of_birth' => '2020-05-15',
                    'gender' => 'male',
                ],
            ],
        ]);

        $child = Child::where('first_name', 'Ahmad')->first();
        $pivotData = $this->user->children()->where('child_id', $child->id)->first()->pivot;

        expect($pivotData->relationship_type)->toBe('Parent');
    });
});
