<?php

use App\Actions\Undertaking\GetActiveLetterForTenantAction;
use App\Models\LetterOfUndertaking;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('get active letter action returns letter with description', function () {
    $tenant = Tenant::factory()->create([
        'require_undertaking_agreement' => true,
    ]);

    $admin = User::factory()->create();
    $admin->tenants()->attach($tenant->id);
    $admin->update(['current_tenant_id' => $tenant->id]);

    $letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Parent Agreement',
        'description' => 'This is a summary of what parents need to agree to.',
        'content' => '<p>Full letter content here</p>',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $action = new GetActiveLetterForTenantAction;
    $activeLetter = $action->execute($tenant);

    expect($activeLetter)->not->toBeNull()
        ->and($activeLetter->title)->toBe('Parent Agreement')
        ->and($activeLetter->description)->toBe('This is a summary of what parents need to agree to.')
        ->and($activeLetter->content)->toContain('Full letter content here');
});

test('get active letter action returns letter without description', function () {
    $tenant = Tenant::factory()->create([
        'require_undertaking_agreement' => true,
    ]);

    $admin = User::factory()->create();
    $admin->tenants()->attach($tenant->id);
    $admin->update(['current_tenant_id' => $tenant->id]);

    $letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Parent Agreement',
        'description' => null,
        'content' => '<p>Full letter content here</p>',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $action = new GetActiveLetterForTenantAction;
    $activeLetter = $action->execute($tenant);

    expect($activeLetter)->not->toBeNull()
        ->and($activeLetter->title)->toBe('Parent Agreement')
        ->and($activeLetter->description)->toBeNull()
        ->and($activeLetter->content)->toContain('Full letter content here');
});
