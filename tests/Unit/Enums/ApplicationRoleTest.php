<?php

use App\Enums\ApplicationRole;

it('provides kebab-case role values with Pascal-style labels', function () {
    expect(ApplicationRole::options())->toMatchArray([
        'super-admin' => 'Super Admin',
        'accountant' => 'Accountant',
        'parent' => 'Parent',
    ]);
});

it('resolves canonical labels from legacy and canonical role names', function () {
    expect(ApplicationRole::labelFor('super-admin'))->toBe('Super Admin')
        ->and(ApplicationRole::labelFor('Super Admin'))->toBe('Super Admin')
        ->and(ApplicationRole::labelFor('accountant'))->toBe('Accountant');
});
