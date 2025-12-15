<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows tenant menu in topbar next to logo', function () {
    /** @var Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $this->actingAs($user);

    $response = $this->followingRedirects()->get('/dashboard');
    $response->assertStatus(200);

    // Tenant name from seeder should appear in topbar via tenant switcher
    $response->assertSee('Default Tenant');

    // Verify the custom tenant switcher is rendered
    $content = $response->getContent();
    expect($content)->toContain('Centre:'); // Label for tenant switcher
    expect($content)->toContain('wire:model.live="selectedTenant"'); // Livewire binding
});
