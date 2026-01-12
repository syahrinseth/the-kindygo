<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows tenant menu in topbar next to logo', function () {
    /** @var Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $this->actingAs($user);

    $response = $this->followingRedirects()->get('/admin/dashboard');
    $response->assertStatus(200);

    // Tenant name from seeder should appear in topbar via tenant switcher
    $response->assertSee('Test Tenant', false); // Don't escape HTML

    // Verify the custom tenant switcher is rendered
    $content = $response->getContent();
    expect($content)->toContain('id="tenant-switcher"'); // Tenant switcher element
    expect($content)->toContain('wire:model.live="selectedTenant"'); // Livewire binding
});
