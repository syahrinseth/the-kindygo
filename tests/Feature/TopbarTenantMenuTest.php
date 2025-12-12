<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows tenant menu in topbar next to logo', function () {
    /** @var Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $user = \App\Models\User::where('email', 'test@example.com')->first();
    $this->actingAs($user);

    $response = $this->followingRedirects()->get('/app');
    $response->assertStatus(200);

    // Tenant name from seeder should appear in topbar
    $response->assertSee('Default Tenant');

    // Ensure only one tenant menu is rendered on the page
    $content = $response->getContent();
    // Verify topbar contains a tenant menu trigger
    $topbarStart = strpos($content, '<nav class="fi-topbar');
    $topbarEnd = strpos($content, '</nav>', $topbarStart);
    $topbarHtml = substr($content, $topbarStart, $topbarEnd - $topbarStart + 6);
    expect(substr_count($topbarHtml, 'fi-tenant-menu-trigger'))->toBeGreaterThanOrEqual(1);

    // Verify sidebar does NOT contain a tenant menu
    $sidebarStart = strpos($content, '<aside');
    $sidebarEnd = strpos($content, '</aside>', $sidebarStart);
    $sidebarHtml = $sidebarStart !== false && $sidebarEnd !== false
        ? substr($content, $sidebarStart, $sidebarEnd - $sidebarStart + 8)
        : '';

    expect(substr_count($sidebarHtml, 'fi-tenant-menu'))->toBe(0);
});
