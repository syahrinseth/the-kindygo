<?php

use App\Actions\Undertaking\DeactivateOtherLettersAction;
use App\Models\LetterOfUndertaking;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deactivates all other active letters for same tenant', function () {
    $tenant = Tenant::factory()->create();

    // Create 3 active letters for the tenant
    $letter1 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'title' => 'Letter 1',
    ]);

    $letter2 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'title' => 'Letter 2',
    ]);

    $letter3 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'title' => 'Letter 3',
    ]);

    $action = new DeactivateOtherLettersAction;

    // Activate letter2 and deactivate others
    $action->execute($letter2);

    $letter1->refresh();
    $letter2->refresh();
    $letter3->refresh();

    expect($letter1->is_active)->toBeFalse()
        ->and($letter2->is_active)->toBeTrue() // Should remain active
        ->and($letter3->is_active)->toBeFalse();
});

it('does not affect letters from other tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    // Letter for tenant1
    $letter1 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant1->id,
        'is_active' => true,
    ]);

    // Letter for tenant2
    $letter2 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant2->id,
        'is_active' => true,
    ]);

    // Another letter for tenant1
    $letter3 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant1->id,
        'is_active' => true,
    ]);

    $action = new DeactivateOtherLettersAction;

    // Activate letter3 for tenant1
    $action->execute($letter3);

    $letter1->refresh();
    $letter2->refresh();
    $letter3->refresh();

    expect($letter1->is_active)->toBeFalse() // Deactivated (same tenant)
        ->and($letter2->is_active)->toBeTrue() // Unchanged (different tenant)
        ->and($letter3->is_active)->toBeTrue(); // Remains active
});

it('does not deactivate already inactive letters', function () {
    $tenant = Tenant::factory()->create();

    $activeLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $inactiveLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => false,
    ]);

    $newActiveLetter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $action = new DeactivateOtherLettersAction;
    $action->execute($newActiveLetter);

    $activeLetter->refresh();
    $inactiveLetter->refresh();
    $newActiveLetter->refresh();

    expect($activeLetter->is_active)->toBeFalse() // Deactivated
        ->and($inactiveLetter->is_active)->toBeFalse() // Remains inactive
        ->and($newActiveLetter->is_active)->toBeTrue(); // Active
});

it('handles case when only one letter exists', function () {
    $tenant = Tenant::factory()->create();

    $letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $action = new DeactivateOtherLettersAction;
    $action->execute($letter);

    $letter->refresh();

    // Should remain active (no other letters to deactivate)
    expect($letter->is_active)->toBeTrue();
});

it('bypasses tenant scope to update all letters', function () {
    $tenant = Tenant::factory()->create();

    $letter1 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $letter2 = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $action = new DeactivateOtherLettersAction;

    // Execute without setting current tenant context
    $action->execute($letter2);

    $letter1->refresh();
    $letter2->refresh();

    // Should still deactivate letter1 even without tenant scope
    expect($letter1->is_active)->toBeFalse()
        ->and($letter2->is_active)->toBeTrue();
});

it('updates multiple active letters at once', function () {
    $tenant = Tenant::factory()->create();

    // Create 5 active letters
    $letters = LetterOfUndertaking::factory()->count(5)->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    $selectedLetter = $letters->last();

    $action = new DeactivateOtherLettersAction;
    $action->execute($selectedLetter);

    // Refresh all letters
    $letters->each->refresh();

    // Only the selected letter should be active
    $activeCount = $letters->where('is_active', true)->count();
    expect($activeCount)->toBe(1)
        ->and($selectedLetter->fresh()->is_active)->toBeTrue();
});
