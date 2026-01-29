<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows seeded admin user to login and redirects to admin panel', function () {
    /** @var Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password@123',
    ]);

    // Test user has Admin role, so should redirect to /admin
    $response->assertRedirect('/admin');
    $this->assertAuthenticated();
});

it('allows seeded parent user to login and redirects to dashboard', function () {
    /** @var Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $response = $this->post('/login', [
        'email' => 'parent@example.com',
        'password' => 'password@123',
    ]);

    // Parent user should redirect to /dashboard
    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});
