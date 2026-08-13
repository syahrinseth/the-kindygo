<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'principal', 'teacher', 'parent', 'staff'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

it('requires registration for an incomplete parent', function () {
    $user = User::factory()->create(['profile_completed' => false]);
    $user->assignRole('parent');

    expect($user->requiresParentRegistration())->toBeTrue();
});

it('does not require registration for a completed parent', function () {
    $user = User::factory()->create(['profile_completed' => true]);
    $user->assignRole('parent');

    expect($user->requiresParentRegistration())->toBeFalse();
});

it('does not require registration for admin roles', function (string $role) {
    $user = User::factory()->create(['profile_completed' => false]);
    $user->assignRole($role);

    expect($user->requiresParentRegistration())->toBeFalse();
})->with(['super-admin', 'admin', 'principal', 'teacher']);

it('gives an admin role priority over the parent role', function () {
    $user = User::factory()->create(['profile_completed' => false]);
    $user->assignRole(['super-admin', 'parent']);

    expect($user->requiresParentRegistration())->toBeFalse();
});

it('does not require parent registration for an incomplete non-parent', function () {
    $user = User::factory()->create(['profile_completed' => false]);
    $user->assignRole('staff');

    expect($user->requiresParentRegistration())->toBeFalse();
});
