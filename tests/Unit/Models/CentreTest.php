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
