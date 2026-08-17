<?php

use App\Models\User;

it('soft deletes users and excludes them from normal queries', function () {
    $user = User::factory()->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id)?->trashed())->toBeTrue();
});

it('restores a soft deleted user', function () {
    $user = User::factory()->create();

    $user->delete();
    $deletedUser = User::withTrashed()->findOrFail($user->id);

    $deletedUser->restore();

    expect(User::find($user->id))->not->toBeNull();
    expect($deletedUser->fresh()->trashed())->toBeFalse();
});
