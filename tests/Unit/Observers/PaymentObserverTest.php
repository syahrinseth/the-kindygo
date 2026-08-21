<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->tenant = Tenant::factory()->create();
    test()->user = User::factory()->create(['current_tenant_id' => test()->tenant->id]);
    test()->tenant->users()->attach(test()->user->id);
    test()->actingAs(test()->user);

    test()->centre = Centre::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->child = Child::factory()->create();
    test()->child->tenants()->attach(test()->tenant->id);
});

it('updates invoice status to PAID when payment status changes to PAID and invoice is fully paid', function () {
    // Create invoice with total 10000
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    // Create payment with PENDING status
    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PENDING,
    ]);

    // Attach invoice to payment (simulating allocation)
    $payment->invoices()->attach($invoice->id, ['amount' => 10000]);

    // Update payment status to PAID - this should trigger the observer
    $payment->update(['status' => PaymentStatus::PAID]);

    // Refresh invoice to get latest status
    $invoice->refresh();

    // Assert invoice status changed to PAID
    expect($invoice->status)->toBe(InvoiceStatus::PAID);
});

it('updates invoice status to PARTIALLY_PAID when payment status changes to PAID and invoice is partially paid', function () {
    // Create invoice with total 10000
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    // Create payment with PENDING status (only pays 5000 of 10000)
    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 5000,
        'status' => PaymentStatus::PENDING,
    ]);

    // Attach invoice to payment (simulating partial allocation)
    $payment->invoices()->attach($invoice->id, ['amount' => 5000]);

    // Update payment status to PAID - this should trigger the observer
    $payment->update(['status' => PaymentStatus::PAID]);

    // Refresh invoice to get latest status
    $invoice->refresh();

    // Assert invoice status changed to PARTIALLY_PAID
    expect($invoice->status)->toBe(InvoiceStatus::PARTIALLY_PAID);
});

it('updates multiple invoice statuses when payment covers multiple invoices', function () {
    // Create two invoices
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 5000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 1',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item 2',
        'price' => 5000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 5000,
        'paid_amount' => 0,
        'balance_amount' => 5000,
        'paid' => false,
    ]);

    // Create payment covering both invoices
    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 15000,
        'status' => PaymentStatus::PENDING,
    ]);

    // Attach both invoices to payment
    $payment->invoices()->attach($invoice1->id, ['amount' => 10000]);
    $payment->invoices()->attach($invoice2->id, ['amount' => 5000]);

    // Update payment status to PAID
    $payment->update(['status' => PaymentStatus::PAID]);

    // Refresh invoices
    $invoice1->refresh();
    $invoice2->refresh();

    // Both invoices should be fully paid
    expect($invoice1->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice2->status)->toBe(InvoiceStatus::PAID);
});

it('does not trigger invoice status update when payment status changes to FAILED', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PENDING,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 10000]);

    // Update payment status to FAILED - should NOT update invoice status
    $payment->update(['status' => PaymentStatus::FAILED]);

    $invoice->refresh();

    // Invoice status should remain PENDING
    expect($invoice->status)->toBe(InvoiceStatus::PENDING);
});

it('does not trigger invoice status update when payment status changes to CANCELLED', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PENDING,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 10000]);

    // Update payment status to CANCELLED - should NOT update invoice status
    $payment->update(['status' => PaymentStatus::CANCELLED]);

    $invoice->refresh();

    // Invoice status should remain PENDING
    expect($invoice->status)->toBe(InvoiceStatus::PENDING);
});

it('handles payment with no invoices gracefully', function () {
    Log::shouldReceive('info')->zeroOrMoreTimes();
    Log::shouldReceive('warning')
        ->withArgs(function ($message, $context) {
            // Accept warnings from both updating and updated events
            return in_array($message, [
                'Payment marked as PAID but no invoices attached',
                'Payment status changed to PAID but no invoices attached',
            ]);
        });

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PENDING,
    ]);

    // Update payment status to PAID without attaching any invoices
    $payment->update(['status' => PaymentStatus::PAID]);

    // Should not throw exception - just log a warning
    expect(true)->toBeTrue();
});

it('logs invoice status update when payment status changes to PAID', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PENDING,
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 10000]);

    // Allow all info logs but verify the specific one we care about
    Log::shouldReceive('info')
        ->withArgs(function ($message, $context) use ($payment, $invoice) {
            if ($message === 'Invoice statuses updated via payment observer') {
                return $context['payment_id'] === $payment->id
                    && $context['invoice_count'] === 1
                    && in_array($invoice->id, $context['invoice_ids']);
            }

            return true; // Allow other info logs
        });

    // Update payment status to PAID
    $payment->update(['status' => PaymentStatus::PAID]);

    expect(true)->toBeTrue();
});

it('does not trigger when payment is created with PAID status initially', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => test()->tenant->id,
        'centre_id' => test()->centre->id,
        'user_id' => test()->user->id,
        'total_amount' => 10000,
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => test()->child->id,
        'name' => 'Test Item',
        'price' => 10000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 10000,
        'paid_amount' => 0,
        'balance_amount' => 10000,
        'paid' => false,
    ]);

    // Create payment with PAID status from the start
    $payment = Payment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->user->id,
        'amount' => 10000,
        'status' => PaymentStatus::PAID, // Already PAID
    ]);

    $payment->invoices()->attach($invoice->id, ['amount' => 10000]);

    // Invoice status should remain PENDING because observer doesn't fire for creation
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PENDING);
});
