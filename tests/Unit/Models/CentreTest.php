<?php

use App\Models\Centre;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has a payments relationship with allocated_amount pivot', function () {
    $centre = Centre::factory()->create();
    $payment1 = Payment::factory()->create();
    $payment2 = Payment::factory()->create();

    $centre->payments()->attach($payment1->id, ['allocated_amount' => 5000]);
    $centre->payments()->attach($payment2->id, ['allocated_amount' => 10000]);

    $centre->refresh();

    expect($centre->payments)->toHaveCount(2);
    expect($centre->payments->first()->pivot->allocated_amount)->toBe(5000);
    expect($centre->payments->last()->pivot->allocated_amount)->toBe(10000);
});

it('can access payments from the centre perspective', function () {
    $centre = Centre::factory()->create();
    $payment = Payment::factory()->create();

    $payment->centres()->attach($centre->id, ['allocated_amount' => 15000]);

    $centre->refresh();

    expect($centre->payments)->toHaveCount(1);
    expect($centre->payments->first()->id)->toBe($payment->id);
    expect($centre->payments->first()->pivot->allocated_amount)->toBe(15000);
});

it('returns empty collection when centre has no payments', function () {
    $centre = Centre::factory()->create();

    expect($centre->payments)->toBeEmpty();
});

// Centre Code Generation Tests

it('generates default code when centre is null', function () {
    $code = Centre::generateCentreCode(null);

    expect($code)->toBe('CTR');
});

it('uses existing code field when available', function () {
    $centre = Centre::factory()->make(['code' => 'MB']);

    $code = Centre::generateCentreCode($centre);

    expect($code)->toBe('MB');
});

it('converts existing code to uppercase', function () {
    $centre = Centre::factory()->make(['code' => 'mb']);

    $code = Centre::generateCentreCode($centre);

    expect($code)->toBe('MB');
});

it('generates code from multi-word centre name using first letters', function () {
    $centre = Centre::factory()->make(['name' => 'Happy Kids Centre', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // H + K + C = HKC
    expect($code)->toBe('HKC');
});

it('generates code from two-word name', function () {
    $centre = Centre::factory()->make(['name' => 'Sunny Preschool', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // S + P = SP
    expect($code)->toBe('SP');
});

it('generates code from four-word name (limited to 4 chars)', function () {
    $centre = Centre::factory()->make(['name' => 'Little Stars Kindergarten School', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // L + S + K + S = LSKS (limited to 4 chars)
    expect($code)->toBe('LSKS');
});

it('generates code from single word by taking first letters', function () {
    $centre = Centre::factory()->make(['name' => 'Wonderland', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Single word: take first 3 letters → WON
    expect($code)->toBe('WON');
});

it('handles short acronym names', function () {
    $centre = Centre::factory()->make(['name' => 'ABC', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Single word: take all letters → ABC
    expect($code)->toBe('ABC');
});

it('handles very short names', function () {
    $centre = Centre::factory()->make(['name' => 'KG', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Single word with 2 chars → KG
    expect($code)->toBe('KG');
});

it('handles single letter name with padding', function () {
    $centre = Centre::factory()->make(['name' => 'A', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Single letter padded to minimum 2 chars → A0
    expect($code)->toBe('A0');
});

it('handles special characters by removing them', function () {
    $centre = Centre::factory()->make(['name' => "Little Stars' Kindergarten", 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Special char removed: Little Stars Kindergarten → L + S + K = LSK
    expect($code)->toBe('LSK');
});

it('handles names with numbers', function () {
    $centre = Centre::factory()->make(['name' => 'Centre 123 Kids', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // C + 1 + K = C1K
    expect($code)->toBe('C1K');
});

it('handles empty name gracefully', function () {
    $centre = Centre::factory()->make(['name' => '', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Empty defaults to 'Centre' → CTR
    expect($code)->toBe('CTR');
});

it('handles name with only special characters', function () {
    $centre = Centre::factory()->make(['name' => '!@#$%', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // All special chars removed → defaults to CTR
    expect($code)->toBe('CTR');
});

it('handles very long centre names (limits to 4 characters)', function () {
    $centre = Centre::factory()->make(['name' => 'Wonderful Amazing Beautiful Fantastic Centre', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // W + A + B + F = WABF (limited to 4 chars)
    expect($code)->toBe('WABF');
});

it('handles common words like "and" and "the"', function () {
    $centre = Centre::factory()->make(['name' => 'Marvin and Sons Centre', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // M + A + S + C = MASC
    expect($code)->toBe('MASC');
});

it('handles names with commas', function () {
    $centre = Centre::factory()->make(['name' => 'Witting, Lind and Herman Centre', 'code' => null]);

    $code = Centre::generateCentreCode($centre);

    // Comma removed: Witting Lind and Herman Centre → W + L + A + H = WLAH
    expect($code)->toBe('WLAH');
});

it('generates consistent codes for same centre', function () {
    $centre = Centre::factory()->make(['name' => 'Happy Kids Centre', 'code' => null]);

    $code1 = Centre::generateCentreCode($centre);
    $code2 = Centre::generateCentreCode($centre);

    expect($code1)->toBe($code2);
});

it('prioritizes code field over name generation', function () {
    $centre = Centre::factory()->make([
        'name' => 'Happy Kids Centre',
        'code' => 'CUSTOM',
    ]);

    $code = Centre::generateCentreCode($centre);

    expect($code)->toBe('CUSTOM')->not->toBe('HKC');
});

it('generates code for real-world centre names', function () {
    $testCases = [
        ['name' => 'The Learning Tree Preschool', 'expected' => 'TLTP'], // T + L + T + P
        ['name' => 'Future Leaders Kindergarten', 'expected' => 'FLK'], // F + L + K
        ['name' => 'Stepping Stones Childcare', 'expected' => 'SSC'], // S + S + C
        ['name' => 'Wonderkids Academy', 'expected' => 'WA'], // W + A
        ['name' => 'ABC Learning Centre', 'expected' => 'ALC'], // A + L + C (ABC as single word)
        ['name' => 'Rainbow Kids', 'expected' => 'RK'], // R + K
    ];

    foreach ($testCases as $case) {
        $centre = Centre::factory()->make(['name' => $case['name'], 'code' => null]);
        $code = Centre::generateCentreCode($centre);
        expect($code)->toBe($case['expected'], "Failed for: {$case['name']}");
    }
});

it('handles mixed case names consistently', function () {
    $centre1 = Centre::factory()->make(['name' => 'Happy Kids Centre', 'code' => null]);
    $centre2 = Centre::factory()->make(['name' => 'HAPPY KIDS CENTRE', 'code' => null]);
    $centre3 = Centre::factory()->make(['name' => 'happy kids centre', 'code' => null]);

    expect(Centre::generateCentreCode($centre1))->toBe('HKC');
    expect(Centre::generateCentreCode($centre2))->toBe('HKC');
    expect(Centre::generateCentreCode($centre3))->toBe('HKC');
});
