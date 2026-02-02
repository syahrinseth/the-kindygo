<?php

use App\Constants\TokenAbility;
use App\Models\PushNotification;
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

describe('GET /api/v1/notifications', function () {
    it('returns list of notifications for authenticated user', function () {
        PushNotification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/notifications');

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
                    'total' => 5,
                ],
            ]);
    });

    it('filters unread notifications only', function () {
        PushNotification::factory()->unread()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
        PushNotification::factory()->read()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/notifications?unread_only=1');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 3,
                ],
            ]);
    });

    it('only returns notifications belonging to authenticated user', function () {
        PushNotification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
        // Create notifications for another user
        $otherUser = User::factory()->create();
        PushNotification::factory()->count(3)->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 2,
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/notifications/unread-count', function () {
    it('returns count of unread notifications', function () {
        PushNotification::factory()->unread()->count(4)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
        PushNotification::factory()->read()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 4,
                ],
            ]);
    });

    it('returns zero when no unread notifications', function () {
        PushNotification::factory()->read()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertUnauthorized();
    });
});

describe('PUT /api/v1/notifications/{notification}/mark-as-read', function () {
    it('marks notification as read', function () {
        $notification = PushNotification::factory()->unread()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson("/api/v1/notifications/{$notification->id}/mark-as-read");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Notification marked as read.',
            ]);

        $notification->refresh();
        expect($notification->is_read)->toBeTrue();
        expect($notification->read_at)->not->toBeNull();
    });

    it('returns 404 for non-existent notification', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson('/api/v1/notifications/99999/mark-as-read');

        $response->assertNotFound();
    });

    it('returns 404 for notification not belonging to user', function () {
        $otherUser = User::factory()->create();
        $notification = PushNotification::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->putJson("/api/v1/notifications/{$notification->id}/mark-as-read");

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $notification = PushNotification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->putJson("/api/v1/notifications/{$notification->id}/mark-as-read");

        $response->assertUnauthorized();
    });
});

describe('POST /api/v1/notifications/mark-all-as-read', function () {
    it('marks all notifications as read', function () {
        PushNotification::factory()->unread()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/notifications/mark-all-as-read');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $unreadCount = PushNotification::where('user_id', $this->user->id)
            ->where('is_read', false)
            ->count();

        expect($unreadCount)->toBe(0);
    });

    it('returns success even when no unread notifications', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->postJson('/api/v1/notifications/mark-all-as-read');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/notifications/mark-all-as-read');

        $response->assertUnauthorized();
    });
});
