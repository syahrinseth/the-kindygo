<?php

use App\Models\FamilyMember;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\MyKadNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats a twelve digit MyKad number', function () {
    expect(MyKadNumber::format('900101011234'))->toBe('900101-01-1234')
        ->and(MyKadNumber::format('900101 01 1234'))->toBe('900101-01-1234')
        ->and(MyKadNumber::format('900101-01-1234'))->toBe('900101-01-1234');
});

it('preserves null, blank, and malformed values', function () {
    expect(MyKadNumber::format(null))->toBeNull()
        ->and(MyKadNumber::format(''))->toBe('')
        ->and(MyKadNumber::format('A12345678'))->toBe('A12345678');
});

it('formats profile and family member NRIC values when they are saved', function () {
    $user = User::factory()->create();

    $profile = UserProfile::create([
        'user_id' => $user->id,
        'nric' => '900101011234',
    ]);
    $familyMember = FamilyMember::factory()->create([
        'user_id' => $user->id,
        'nric' => '880101010001',
    ]);

    expect($profile->nric)->toBe('900101-01-1234')
        ->and($familyMember->nric)->toBe('880101-01-0001');
});
