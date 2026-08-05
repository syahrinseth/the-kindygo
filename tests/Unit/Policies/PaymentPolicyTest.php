<?php

use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create roles
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'principal']);
    Role::create(['name' => 'parent']);
    Role::create(['name' => 'teacher']);
});

it('allows Super Admin to view payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('super-admin');
    $user->refresh(); // Refresh to load roles

    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeTrue();
});

it('allows Admin to view payment in their tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('admin');
    $user->refresh();

    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeTrue();
});

it('denies Admin from viewing payment in another tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant1->id;
    $user->save();
    $user->assignRole('admin');
    $user->refresh();

    $payment = Payment::factory()->create(['tenant_id' => $tenant2->id]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeFalse();
});

it('allows Principal to view payment for their centre', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre->id);

    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);
    $payment->centres()->attach($centre->id, ['allocated_amount' => 10000]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeTrue();
});

it('denies Principal from viewing payment for centres they do not manage', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre1->id);

    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);
    $payment->centres()->attach($centre2->id, ['allocated_amount' => 10000]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeFalse();
});

it('allows Principal to view multi-centre payment if they manage at least one centre', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre1->id);

    $payment = Payment::factory()->create(['tenant_id' => $tenant->id]);
    $payment->centres()->attach($centre1->id, ['allocated_amount' => 10000]);
    $payment->centres()->attach($centre2->id, ['allocated_amount' => 15000]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeTrue();
});

it('allows Parent to view their own payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('parent');
    $user->refresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
    ]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeTrue();
});

it('denies Parent from viewing another user\'s payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $otherUser = User::factory()->create();
    $otherUser->current_tenant_id = $tenant->id;
    $otherUser->save();
    $user->assignRole('parent');
    $user->refresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $otherUser->id,
    ]);

    $policy = new PaymentPolicy;

    expect($policy->view($user, $payment))->toBeFalse();
});

it('allows Super Admin to update payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('super-admin');
    $user->refresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);

    $policy = new PaymentPolicy;

    expect($policy->update($user, $payment))->toBeTrue();
});

it('allows Principal to update pending payment for their centre', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre->id);

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);
    $payment->centres()->attach($centre->id, ['allocated_amount' => 10000]);

    $policy = new PaymentPolicy;

    expect($policy->update($user, $payment))->toBeTrue();
});

it('denies Principal from updating paid payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre->id);

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PAID,
    ]);
    $payment->centres()->attach($centre->id, ['allocated_amount' => 10000]);

    $policy = new PaymentPolicy;

    expect($policy->update($user, $payment))->toBeFalse();
});

it('allows Super Admin to delete pending payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('super-admin');
    $user->refresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);

    $policy = new PaymentPolicy;

    expect($policy->delete($user, $payment))->toBeTrue();
});

it('allows Super Admin to delete failed payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('super-admin');
    $user->refresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::FAILED,
    ]);

    $policy = new PaymentPolicy;

    expect($policy->delete($user, $payment))->toBeTrue();
});

it('denies Super Admin from deleting paid payment', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('super-admin');
    $user->refresh();

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    $policy = new PaymentPolicy;

    expect($policy->delete($user, $payment))->toBeFalse();
});

it('allows Principal to delete pending payment for their centre', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre->id);

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);
    $payment->centres()->attach($centre->id, ['allocated_amount' => 10000]);

    $policy = new PaymentPolicy;

    expect($policy->delete($user, $payment))->toBeTrue();
});

it('denies Principal from deleting payment for centres they do not manage', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();
    $user->assignRole('principal');
    $user->refresh();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $user->centres()->attach($centre1->id);

    $payment = Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);
    $payment->centres()->attach($centre2->id, ['allocated_amount' => 10000]);

    $policy = new PaymentPolicy;

    expect($policy->delete($user, $payment))->toBeFalse();
});
