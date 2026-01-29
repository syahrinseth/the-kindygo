<?php

use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    $this->user->current_tenant_id = $this->tenant->id;
    $this->user->save();

    test()->actingAs($this->user);
});

it('widget queries children through relationship', function () {
    $child = Child::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $child->tenants()->attach($this->tenant->id);

    $this->user->children()->attach($child);

    // Verify user can load children
    $children = $this->user->children()->get();
    expect($children)->toHaveCount(1);
    expect($children->first()->first_name)->toBe('John');
});

it('children relationship works both ways', function () {
    $child1 = Child::factory()->create();
    $child2 = Child::factory()->create();

    $child1->tenants()->attach($this->tenant->id);
    $child2->tenants()->attach($this->tenant->id);

    $this->user->children()->attach([$child1->id, $child2->id]);

    $children = $this->user->children()->get();
    expect($children)->toHaveCount(2);
});

it('returns empty collection when user has no children', function () {
    $children = $this->user->children()->get();
    expect($children)->toBeEmpty();
});

it('child data is correctly structured', function () {
    $child = Child::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'mykid_no' => 'MK789123',
        'date_of_birth' => now()->subYears(3),
    ]);

    $child->tenants()->attach($this->tenant->id);

    $this->user->children()->attach($child);

    $loadedChild = $this->user->children()->first();

    expect($loadedChild->first_name)->toBe('Jane');
    expect($loadedChild->last_name)->toBe('Smith');
    expect($loadedChild->mykid_no)->toBe('MK789123');
    expect($loadedChild->date_of_birth->format('Y-m-d'))->toBe(now()->subYears(3)->format('Y-m-d'));
});
