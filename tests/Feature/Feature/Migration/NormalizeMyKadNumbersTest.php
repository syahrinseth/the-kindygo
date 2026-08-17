<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('formats existing profile, family member, and registration draft MyKad numbers', function () {
    $user = User::factory()->create([
        'registration_data' => [
            'step_1' => [
                'mykad_number' => '920202021234',
            ],
        ],
    ]);
    $malformedUser = User::factory()->create([
        'registration_data' => [
            'step_1' => [
                'mykad_number' => 'A12345678',
            ],
        ],
    ]);

    DB::table('user_profiles')->insert([
        'user_id' => $user->id,
        'nric' => '900101011234',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('family_members')->insert([
        'user_id' => $user->id,
        'relationship_type' => 'spouse',
        'nric' => '880101 01 0001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('user_profiles')->insert([
        'user_id' => $malformedUser->id,
        'nric' => 'A12345678',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_17_042416_normalize_mykad_numbers.php');
    $migration->up();

    expect(DB::table('user_profiles')->where('user_id', $user->id)->value('nric'))->toBe('900101-01-1234')
        ->and(DB::table('family_members')->where('user_id', $user->id)->value('nric'))->toBe('880101-01-0001')
        ->and(User::find($user->id)->getRegistrationData('step_1.mykad_number'))->toBe('920202-02-1234')
        ->and(DB::table('user_profiles')->where('user_id', $malformedUser->id)->value('nric'))->toBe('A12345678')
        ->and(User::find($malformedUser->id)->getRegistrationData('step_1.mykad_number'))->toBe('A12345678');
});
