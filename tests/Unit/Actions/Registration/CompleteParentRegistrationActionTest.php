<?php

use App\Actions\Registration\CompleteParentRegistrationAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->action = new CompleteParentRegistrationAction;
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'profile_completed' => false,
        'registration_step' => 3,
        'registration_token' => Str::random(40),
        'registration_token_expires_at' => now()->addDays(5),
    ]);
    $this->user->tenants()->attach($this->tenant->id);
});

it('sets profile_completed to true', function () {
    expect($this->user->profile_completed)->toBeFalse();

    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    expect($this->user->profile_completed)->toBeTrue();
});

it('sets registration_step to 4', function () {
    expect($this->user->registration_step)->toBe(3);

    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    expect($this->user->registration_step)->toBe(4);
});

it('clears registration_token', function () {
    expect($this->user->registration_token)->not->toBeNull();

    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    expect($this->user->registration_token)->toBeNull();
});

it('clears registration_token_expires_at', function () {
    expect($this->user->registration_token_expires_at)->not->toBeNull();

    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    expect($this->user->registration_token_expires_at)->toBeNull();
});

it('stores completion timestamp in registration_data', function () {
    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    $stepData = $this->user->getRegistrationData('step_4');

    expect($stepData)->toBeArray();
    expect($stepData['completed_at'])->not->toBeNull();
    expect($stepData['tnc_accepted'])->toBeTrue();
    expect($stepData['undertaking_accepted'])->toBeTrue();
});

it('persists all changes to database', function () {
    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    $this->action->execute($this->user, $validated);

    // Fetch fresh instance from database
    $freshUser = User::find($this->user->id);

    expect($freshUser->profile_completed)->toBeTrue();
    expect($freshUser->registration_step)->toBe(4);
    expect($freshUser->registration_token)->toBeNull();
    expect($freshUser->registration_token_expires_at)->toBeNull();
});

it('marks user as having completed registration', function () {
    $validated = [
        'tnc_accepted' => true,
        'undertaking_accepted' => true,
    ];

    expect($this->user->isRegistrationComplete())->toBeFalse();

    $this->action->execute($this->user, $validated);

    $this->user->refresh();

    expect($this->user->isRegistrationComplete())->toBeTrue();
});
