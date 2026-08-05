<?php

use App\Actions\Undertaking\NotifyParentsOfNewLetterAction;
use App\Models\LetterOfUndertaking;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewLetterOfUndertakingNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->action = new NotifyParentsOfNewLetterAction;

    Notification::fake();

    Role::create(['name' => 'parent', 'guard_name' => 'web']);
    Role::create(['name' => 'staff', 'guard_name' => 'web']);

    $this->tenant = Tenant::factory()->create();
    $this->letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
});

it('sends notification to all parents of tenant', function () {
    $parent1 = User::factory()->create();
    $parent1->assignRole('parent');
    $parent1->tenants()->attach($this->tenant->id);

    $parent2 = User::factory()->create();
    $parent2->assignRole('parent');
    $parent2->tenants()->attach($this->tenant->id);

    $this->action->execute($this->letter);

    Notification::assertSentTo([$parent1, $parent2], NewLetterOfUndertakingNotification::class);
});

it('excludes non-parent users', function () {
    $parent = User::factory()->create();
    $parent->assignRole('parent');
    $parent->tenants()->attach($this->tenant->id);

    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->tenants()->attach($this->tenant->id);

    $this->action->execute($this->letter);

    Notification::assertSentTo($parent, NewLetterOfUndertakingNotification::class);
    Notification::assertNotSentTo($staff, NewLetterOfUndertakingNotification::class);
});

it('excludes parents from other tenants', function () {
    $parent = User::factory()->create();
    $parent->assignRole('parent');
    $parent->tenants()->attach($this->tenant->id);

    $otherTenant = Tenant::factory()->create();
    $otherParent = User::factory()->create();
    $otherParent->assignRole('parent');
    $otherParent->tenants()->attach($otherTenant->id);

    $this->action->execute($this->letter);

    Notification::assertSentTo($parent, NewLetterOfUndertakingNotification::class);
    Notification::assertNotSentTo($otherParent, NewLetterOfUndertakingNotification::class);
});

it('sends notification with correct letter data', function () {
    $parent = User::factory()->create();
    $parent->assignRole('parent');
    $parent->tenants()->attach($this->tenant->id);

    $this->action->execute($this->letter);

    Notification::assertSentTo($parent, function (NewLetterOfUndertakingNotification $notification) {
        return $notification->letter->id === $this->letter->id;
    });
});

it('handles tenant with no parents', function () {
    // Create a staff member but no parents
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->tenants()->attach($this->tenant->id);

    $this->action->execute($this->letter);

    Notification::assertNothingSent();
});
