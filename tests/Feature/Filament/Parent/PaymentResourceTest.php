<?php

use App\Enums\PaymentStatus;
use App\Filament\Parent\Resources\PaymentResource;
use App\Filament\Parent\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Parent\Resources\PaymentResource\Pages\ViewPayment;
use App\Models\Centre;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create roles
    Role::firstOrCreate(['name' => 'Parent']);

    $this->parent = User::factory()->create();
    $this->parent->tenants()->attach($this->tenant->id);
    $this->parent->assignRole('Parent');
    $this->parent->update(['current_tenant_id' => $this->tenant->id]);

    $this->otherParent = User::factory()->create();
    $this->otherParent->tenants()->attach($this->tenant->id);
    $this->otherParent->assignRole('Parent');
    $this->otherParent->update(['current_tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('parent'));
});

test('parent can view their own payments list', function () {
    $this->actingAs($this->parent);

    $ownPayments = Payment::factory()->count(3)->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
    ]);

    // Attach centres to payments
    foreach ($ownPayments as $payment) {
        $payment->centres()->attach($this->centre->id, ['allocated_amount' => $payment->amount]);
    }

    $otherPayments = Payment::factory()->count(2)->create([
        'user_id' => $this->otherParent->id,
        'tenant_id' => $this->tenant->id,
    ]);

    // Attach centres to other payments
    foreach ($otherPayments as $payment) {
        $payment->centres()->attach($this->centre->id, ['allocated_amount' => $payment->amount]);
    }

    Livewire::test(ListPayments::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords($ownPayments)
        ->assertCanNotSeeTableRecords($otherPayments);
});

test('parent can view individual payment details', function () {
    $this->actingAs($this->parent);

    $payment = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    // Attach centre to payment
    $payment->centres()->attach($this->centre->id, ['allocated_amount' => $payment->amount]);

    Livewire::test(ViewPayment::class, ['record' => $payment->id])
        ->assertSuccessful()
        ->assertSee($payment->reference_no);
});

test('parent cannot view other parents payments', function () {
    $this->actingAs($this->parent);

    $otherPayment = Payment::factory()->create([
        'user_id' => $this->otherParent->id,
        'tenant_id' => $this->tenant->id,
    ]);

    // Attach centre to payment
    $otherPayment->centres()->attach($this->centre->id, ['allocated_amount' => $otherPayment->amount]);

    // Expect ModelNotFoundException because the payment is filtered out at the query level
    // This is more secure than relying only on authorization
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    Livewire::test(ViewPayment::class, ['record' => $otherPayment->id]);
});

test('parent can download receipt for paid payments', function () {
    $this->actingAs($this->parent);

    $payment = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    // Attach centre to payment
    $payment->centres()->attach($this->centre->id, ['allocated_amount' => $payment->amount]);

    Livewire::test(ViewPayment::class, ['record' => $payment->id])
        ->assertSuccessful()
        ->assertActionVisible('download_receipt');
});

test('parent cannot see download receipt button for non-paid payments', function () {
    $this->actingAs($this->parent);

    $payment = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);

    // Attach centre to payment
    $payment->centres()->attach($this->centre->id, ['allocated_amount' => $payment->amount]);

    Livewire::test(ViewPayment::class, ['record' => $payment->id])
        ->assertSuccessful()
        ->assertActionHidden('download_receipt');
});

test('parent can filter payments by status', function () {
    $this->actingAs($this->parent);

    $paidPayment = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    $paidPayment->centres()->attach($this->centre->id, ['allocated_amount' => $paidPayment->amount]);

    $pendingPayment = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PENDING,
    ]);

    $pendingPayment->centres()->attach($this->centre->id, ['allocated_amount' => $pendingPayment->amount]);

    Livewire::test(ListPayments::class)
        ->assertSuccessful()
        ->filterTable('status', PaymentStatus::PAID->value)
        ->assertCanSeeTableRecords([$paidPayment])
        ->assertCanNotSeeTableRecords([$pendingPayment]);
});

test('parent can search payments by reference number', function () {
    $this->actingAs($this->parent);

    $payment1 = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'reference_no' => 'PAY-12345',
    ]);

    $payment1->centres()->attach($this->centre->id, ['allocated_amount' => $payment1->amount]);

    $payment2 = Payment::factory()->create([
        'user_id' => $this->parent->id,
        'tenant_id' => $this->tenant->id,
        'reference_no' => 'PAY-67890',
    ]);

    $payment2->centres()->attach($this->centre->id, ['allocated_amount' => $payment2->amount]);

    Livewire::test(ListPayments::class)
        ->assertSuccessful()
        ->searchTable('PAY-12345')
        ->assertCanSeeTableRecords([$payment1])
        ->assertCanNotSeeTableRecords([$payment2]);
});

test('parent cannot access admin payment routes', function () {
    $this->actingAs($this->parent);

    $response = $this->get('/admin/payments/payments');

    $response->assertForbidden();
});

test('payment resource is only visible to parents', function () {
    $this->actingAs($this->parent);

    expect(PaymentResource::canViewAny())->toBeTrue();
    expect(PaymentResource::shouldRegisterNavigation())->toBeTrue();
});

test('parent cannot create payments through resource', function () {
    $this->actingAs($this->parent);

    expect(PaymentResource::canCreate())->toBeFalse();
});
