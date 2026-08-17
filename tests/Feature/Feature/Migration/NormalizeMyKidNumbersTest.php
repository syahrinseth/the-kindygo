<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('formats existing child and registration draft MyKid values', function () {
    $user = User::factory()->create([
        'registration_data' => [
            'step_3' => [
                'children' => [
                    ['mykid_no' => '150101010001'],
                    ['mykid_no' => 'MYKID-1'],
                ],
            ],
        ],
    ]);

    DB::table('children')->insert([
        'first_name' => 'Aisyah',
        'last_name' => 'Ahmad',
        'mykid_no' => '150101 01 0001',
        'date_of_birth' => '2015-01-01',
        'gender' => 'female',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('children')->insert([
        'first_name' => 'Legacy',
        'last_name' => 'Child',
        'mykid_no' => 'MYKID-1',
        'date_of_birth' => '2015-01-01',
        'gender' => 'female',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_17_044725_normalize_mykid_numbers.php');
    $migration->up();

    expect(DB::table('children')->where('first_name', 'Aisyah')->value('mykid_no'))->toBe('150101-01-0001')
        ->and(DB::table('children')->where('first_name', 'Legacy')->value('mykid_no'))->toBe('MYKID-1')
        ->and(User::find($user->id)->getRegistrationData('step_3.children.0.mykid_no'))->toBe('150101-01-0001')
        ->and(User::find($user->id)->getRegistrationData('step_3.children.1.mykid_no'))->toBe('MYKID-1');
});
