<?php

use App\Models\Child;
use App\Support\MalaysianIdentificationNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats MyKad and MyKid compatible twelve digit values', function () {
    expect(MalaysianIdentificationNumber::format('150101010001'))->toBe('150101-01-0001')
        ->and(MalaysianIdentificationNumber::format('150101 01 0001'))->toBe('150101-01-0001')
        ->and(MalaysianIdentificationNumber::format('150101-01-0001'))->toBe('150101-01-0001');
});

it('preserves blank and non-standard legacy MyKid values', function () {
    expect(MalaysianIdentificationNumber::format(null))->toBeNull()
        ->and(MalaysianIdentificationNumber::format(''))->toBe('')
        ->and(MalaysianIdentificationNumber::format('MYKID-1'))->toBe('MYKID-1');
});

it('formats MyKid numbers when children are saved', function () {
    $child = Child::factory()->create(['mykid_no' => '150101010001']);

    expect($child->mykid_no)->toBe('150101-01-0001');
});
