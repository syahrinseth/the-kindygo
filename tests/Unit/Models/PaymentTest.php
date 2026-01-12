<?php

use App\Models\Centre;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('has a centres relationship with allocated_amount pivot', function () {
    $payment = Payment::factory()->create();
    $centre1 = Centre::factory()->create();
    $centre2 = Centre::factory()->create();

    $payment->centres()->attach($centre1->id, ['allocated_amount' => 10000]);
    $payment->centres()->attach($centre2->id, ['allocated_amount' => 20000]);

    $payment->refresh();

    expect($payment->centres)->toHaveCount(2);
    expect($payment->centres->first()->pivot->allocated_amount)->toBe(10000);
    expect($payment->centres->last()->pivot->allocated_amount)->toBe(20000);
});

it('can get centre allocation for a specific centre', function () {
    $payment = Payment::factory()->create();
    $centre = Centre::factory()->create();

    $payment->centres()->attach($centre->id, ['allocated_amount' => 15000]);

    expect($payment->getCentreAllocation($centre->id))->toBe(15000);
});

it('returns zero for centre allocation when centre is not associated', function () {
    $payment = Payment::factory()->create();
    $centre = Centre::factory()->create();

    expect($payment->getCentreAllocation($centre->id))->toBe(0);
});

it('correctly identifies multi-centre payments', function () {
    $payment = Payment::factory()->create();
    $centre1 = Centre::factory()->create();
    $centre2 = Centre::factory()->create();

    $payment->centres()->attach($centre1->id, ['allocated_amount' => 10000]);
    $payment->centres()->attach($centre2->id, ['allocated_amount' => 10000]);

    expect($payment->isMultiCentre())->toBeTrue();
});

it('correctly identifies single-centre payments', function () {
    $payment = Payment::factory()->create();
    $centre = Centre::factory()->create();

    $payment->centres()->attach($centre->id, ['allocated_amount' => 20000]);

    expect($payment->isMultiCentre())->toBeFalse();
});

it('correctly identifies payments with no centres', function () {
    $payment = Payment::factory()->create();

    expect($payment->isMultiCentre())->toBeFalse();
});

it('can get the primary centre', function () {
    $payment = Payment::factory()->create();
    $centre1 = Centre::factory()->create(['name' => 'First Centre']);
    $centre2 = Centre::factory()->create(['name' => 'Second Centre']);

    $payment->centres()->attach($centre1->id, ['allocated_amount' => 10000]);
    $payment->centres()->attach($centre2->id, ['allocated_amount' => 20000]);

    $primaryCentre = $payment->getPrimaryCentre();

    expect($primaryCentre)->not->toBeNull();
    expect($primaryCentre->id)->toBe($centre1->id);
    expect($primaryCentre->name)->toBe('First Centre');
});
