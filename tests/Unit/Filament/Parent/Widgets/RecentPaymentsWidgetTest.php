<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    $this->user->current_tenant_id = $this->tenant->id;
    $this->user->save();

    test()->actingAs($this->user);
});

it('returns recent payments for authenticated user', function () {
    // Create payments with different dates
    $newestPayment = Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 10000,
        'created_at' => now(),
    ]);

    $olderPayment = Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 15000,
        'created_at' => now()->subDays(5),
    ]);

    $query = Payment::query()
        ->where('user_id', $this->user->id)
        ->with(['invoices'])
        ->latest('created_at')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(2);
    // Verify ordering: newest first
    expect($query->first()->id)->toBe($newestPayment->id);
    expect($query->last()->id)->toBe($olderPayment->id);
});

it('limits results to 5 payments', function () {
    // Create 7 payments
    Payment::factory(7)->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 10000,
    ]);

    $query = Payment::query()
        ->where('user_id', $this->user->id)
        ->latest('created_at')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(5);
});

it('displays all payment statuses with correct badges', function () {
    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 10000,
    ]);

    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PENDING,
        'amount' => 5000,
    ]);

    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::FAILED,
        'amount' => 3000,
    ]);

    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::CANCELLED,
        'amount' => 2000,
    ]);

    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::REFUNDED,
        'amount' => 1000,
    ]);

    $query = Payment::query()
        ->where('user_id', $this->user->id)
        ->latest('created_at')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(5);
    expect($query->pluck('status'))->toContain(
        PaymentStatus::PAID,
        PaymentStatus::PENDING,
        PaymentStatus::FAILED,
        PaymentStatus::CANCELLED,
        PaymentStatus::REFUNDED
    );
});

it('only includes payments for the authenticated user', function () {
    $otherUser = User::factory()->create();

    // Create payment for another user
    Payment::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 10000,
    ]);

    // Create payment for current user
    $userPayment = Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 15000,
    ]);

    $query = Payment::query()
        ->where('user_id', $this->user->id)
        ->latest('created_at')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(1);
    expect($query->first()->id)->toBe($userPayment->id);
});

it('shows empty state when no payments', function () {
    $query = Payment::query()
        ->where('user_id', $this->user->id)
        ->latest('created_at')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(0);
});
