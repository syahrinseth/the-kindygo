<?php

use App\Actions\Undertaking\GetActiveLetterForTenantAction;
use App\Models\LetterOfUndertaking;
use App\Models\Tenant;

beforeEach(function () {
    $this->action = new GetActiveLetterForTenantAction;
    $this->tenant = Tenant::factory()->create();
});

it('returns active letter for tenant', function () {
    $activeLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    $result = $this->action->execute($this->tenant);

    expect($result)->toBeInstanceOf(LetterOfUndertaking::class);
    expect($result->id)->toBe($activeLetter->id);
    expect($result->is_active)->toBeTrue();
});

it('returns null when no active letter exists', function () {
    LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => false,
    ]);

    $result = $this->action->execute($this->tenant);

    expect($result)->toBeNull();
});

it('ignores letters from other tenants', function () {
    $otherTenant = Tenant::factory()->create();

    LetterOfUndertaking::factory()->create([
        'tenant_id' => $otherTenant->id,
        'is_active' => true,
    ]);

    $result = $this->action->execute($this->tenant);

    expect($result)->toBeNull();
});

it('returns only one active letter even if multiple are marked active', function () {
    LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'version' => 1,
    ]);

    $newestLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'version' => 2,
    ]);

    $result = $this->action->execute($this->tenant);

    expect($result)->toBeInstanceOf(LetterOfUndertaking::class);
});
