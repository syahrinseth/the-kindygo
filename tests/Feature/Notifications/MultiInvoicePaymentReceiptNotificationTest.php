<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MultiInvoicePaymentReceiptNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    // Create roles
    Role::firstOrCreate(['name' => 'Parent']);
    Role::firstOrCreate(['name' => 'Admin']);
});

test('generates parent panel URL for parent users', function () {
    $parent = User::factory()->create();
    $parent->tenants()->attach($this->tenant->id);
    $parent->assignRole('Parent');
    $parent->update(['current_tenant_id' => $this->tenant->id]);

    $payment = Payment::factory()->create([
        'user_id' => $parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    $allocationSummary = [
        'total_invoices' => 1,
        'fully_paid_count' => 1,
        'partially_paid_count' => 0,
        'allocation_details' => [],
    ];

    $notification = new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary);
    $mailMessage = $notification->toMail($parent);

    expect($mailMessage->actionUrl)
        ->toContain('/payments/'.$payment->id)
        ->not->toContain('/admin/payments/payments');
});

test('generates admin panel URL for admin users', function () {
    $admin = User::factory()->create();
    $admin->tenants()->attach($this->tenant->id);
    $admin->assignRole('Admin');
    $admin->update(['current_tenant_id' => $this->tenant->id]);

    $payment = Payment::factory()->create([
        'user_id' => $admin->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    $allocationSummary = [
        'total_invoices' => 1,
        'fully_paid_count' => 1,
        'partially_paid_count' => 0,
        'allocation_details' => [],
    ];

    $notification = new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary);
    $mailMessage = $notification->toMail($admin);

    expect($mailMessage->actionUrl)
        ->toContain('/admin/payments/payments/'.$payment->id);
});

test('notification can be sent to parent users without error', function () {
    Notification::fake();

    $parent = User::factory()->create();
    $parent->tenants()->attach($this->tenant->id);
    $parent->assignRole('Parent');
    $parent->update(['current_tenant_id' => $this->tenant->id]);

    $payment = Payment::factory()->create([
        'user_id' => $parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
    ]);

    $allocationSummary = [
        'total_invoices' => 1,
        'fully_paid_count' => 1,
        'partially_paid_count' => 0,
        'allocation_details' => [
            ['invoice_number' => 'INV-001'],
        ],
    ];

    $parent->notify(new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary));

    Notification::assertSentTo($parent, MultiInvoicePaymentReceiptNotification::class);
});

test('notification contains correct payment information', function () {
    $parent = User::factory()->create();
    $parent->tenants()->attach($this->tenant->id);
    $parent->assignRole('Parent');
    $parent->update(['current_tenant_id' => $this->tenant->id]);

    $payment = Payment::factory()->create([
        'user_id' => $parent->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 10000, // RM100.00
        'reference_no' => 'PAY-TEST-123',
    ]);

    $allocationSummary = [
        'total_invoices' => 2,
        'fully_paid_count' => 1,
        'partially_paid_count' => 1,
        'allocation_details' => [
            ['invoice_number' => 'INV-001'],
            ['invoice_number' => 'INV-002'],
        ],
    ];

    $notification = new MultiInvoicePaymentReceiptNotification($payment, $allocationSummary);
    $mailMessage = $notification->toMail($parent);

    expect($mailMessage->subject)->toContain('Payment Receipt')
        ->and($mailMessage->subject)->toContain('2 Invoice(s)')
        ->and($mailMessage->introLines)->toContain('Your payment has been successfully processed!')
        ->and(collect($mailMessage->introLines)->flatten()->implode(' '))
        ->toContain('RM 100.00')
        ->toContain('PAY-TEST-123')
        ->toContain('INV-001')
        ->toContain('INV-002');
});
