<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows seeded user to login', function () {
    /** @var Tests\TestCase $this */
    $this->seed(DatabaseSeeder::class);

    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password@123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();
});
