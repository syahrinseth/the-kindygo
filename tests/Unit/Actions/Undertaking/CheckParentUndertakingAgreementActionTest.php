<?php

use App\Actions\Undertaking\CheckParentUndertakingAgreementAction;
use App\Models\LetterOfUndertaking;
use App\Models\ParentUndertakingAgreement;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->action = new CheckParentUndertakingAgreementAction;

    Role::create(['name' => 'Parent', 'guard_name' => 'web']);

    $this->tenant = Tenant::factory()->create([
        'require_undertaking_agreement' => true,
    ]);

    $this->user = User::factory()->create();
    $this->user->assignRole('Parent');
});

it('returns null when tenant does not require undertaking agreement', function () {
    $this->tenant->update(['require_undertaking_agreement' => false]);

    $result = $this->action->execute($this->user, $this->tenant);

    expect($result)->toBeNull();
});

it('returns null when no active letter exists', function () {
    $result = $this->action->execute($this->user, $this->tenant);

    expect($result)->toBeNull();
});

it('returns active letter when not agreed', function () {
    $activeLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    $result = $this->action->execute($this->user, $this->tenant);

    expect($result)->toBeInstanceOf(LetterOfUndertaking::class);
    expect($result->id)->toBe($activeLetter->id);
});

it('returns null when user has already agreed', function () {
    $activeLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    ParentUndertakingAgreement::create([
        'user_id' => $this->user->id,
        'letter_of_undertaking_id' => $activeLetter->id,
        'tenant_id' => $this->tenant->id,
        'agreed_at' => now(),
    ]);

    $result = $this->action->execute($this->user, $this->tenant);

    expect($result)->toBeNull();
});

it('handles legacy agreements with null letter_id', function () {
    ParentUndertakingAgreement::create([
        'user_id' => $this->user->id,
        'letter_of_undertaking_id' => null,
        'tenant_id' => $this->tenant->id,
        'agreed_at' => now(),
    ]);

    $activeLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    $result = $this->action->execute($this->user, $this->tenant);

    // Should return letter because legacy agreement doesn't match specific letter
    expect($result)->toBeInstanceOf(LetterOfUndertaking::class);
    expect($result->id)->toBe($activeLetter->id);
});
