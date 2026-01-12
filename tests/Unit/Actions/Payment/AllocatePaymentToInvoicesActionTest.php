<?php

use App\Actions\Payment\AllocatePaymentToInvoicesAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-01-08');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->tenant->users()->attach($this->user->id);
    $this->actingAs($this->user);

    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->child = Child::factory()->create();
    $this->child->tenants()->attach($this->tenant->id);

    $this->action = new AllocatePaymentToInvoicesAction;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allocates payment to single invoice fully', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 50000, // RM 500
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    $item = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => $this->child->id,
        'name' => 'Test Item',
        'price' => 50000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 50000,
        'paid_amount' => 0,
        'balance_amount' => 50000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'amount' => 50000,
        'status' => PaymentStatus::PAID,
    ]);

    $result = $this->action->execute($payment, collect([$invoice]), 50000);

    expect($result['fully_paid_count'])->toBe(1)
        ->and($result['partially_paid_count'])->toBe(0)
        ->and($result['total_invoices'])->toBe(1);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PAID);

    $item->refresh();
    expect($item->paid_amount)->toBe(50000)
        ->and($item->balance_amount)->toBe(0)
        ->and($item->paid)->toBeTrue();
});

it('allocates payment to multiple invoices using FIFO by due date', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 30000, // RM 300
        'due_at' => now()->subDays(10), // Oldest
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 20000, // RM 200
        'due_at' => now()->subDays(5), // Second oldest
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice3 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 25000, // RM 250
        'due_at' => now()->subDays(2), // Newest
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'child_id' => $this->child->id, 'total' => 30000, 'paid_amount' => 0, 'balance_amount' => 30000]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'child_id' => $this->child->id, 'total' => 20000, 'paid_amount' => 0, 'balance_amount' => 20000]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice3->id, 'child_id' => $this->child->id, 'total' => 25000, 'paid_amount' => 0, 'balance_amount' => 25000]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'amount' => 75000, // RM 750 - enough for invoice1 + invoice2 + invoice3
        'status' => PaymentStatus::PAID,
    ]);

    $result = $this->action->execute($payment, collect([$invoice1, $invoice2, $invoice3]), 75000);

    expect($result['fully_paid_count'])->toBe(3)
        ->and($result['partially_paid_count'])->toBe(0)
        ->and($result['total_invoices'])->toBe(3);

    // Verify FIFO order
    $allocationDetails = $result['allocation_details'];
    expect($allocationDetails[0]['invoice_id'])->toBe($invoice1->id) // Oldest first
        ->and($allocationDetails[1]['invoice_id'])->toBe($invoice2->id) // Second oldest
        ->and($allocationDetails[2]['invoice_id'])->toBe($invoice3->id); // Newest last
});

it('allocates partial payment distributed via FIFO', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 40000, // RM 400
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 30000, // RM 300
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice3 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 20000, // RM 200
        'due_at' => now()->subDays(2),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => $this->child->id,
        'name' => 'Test Item 1',
        'price' => 40000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 40000,
        'paid_amount' => 0,
        'balance_amount' => 40000,
        'paid' => false,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => $this->child->id,
        'name' => 'Test Item 2',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice3->id,
        'child_id' => $this->child->id,
        'name' => 'Test Item 3',
        'price' => 20000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 20000,
        'paid_amount' => 0,
        'balance_amount' => 20000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'amount' => 50000, // RM 500 - only enough for invoice1 + partial invoice2
        'status' => PaymentStatus::PAID,
    ]);

    $result = $this->action->execute($payment, collect([$invoice1, $invoice2, $invoice3]), 50000);

    expect($result['fully_paid_count'])->toBe(1) // Invoice1 fully paid
        ->and($result['partially_paid_count'])->toBe(1) // Invoice2 partially paid
        ->and($result['total_invoices'])->toBe(2); // Invoice3 not touched

    $invoice1->refresh();
    $invoice2->refresh();
    $invoice3->refresh();

    expect($invoice1->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice2->status)->toBe(InvoiceStatus::OVERDUE) // Becomes overdue due to due_at in past and retrieved event
        ->and($invoice3->status)->toBe(InvoiceStatus::OVERDUE); // Also overdue due to due_at in past

    $item1 = $invoice1->invoiceItems->first();
    $item2 = $invoice2->invoiceItems->first();
    $item3 = $invoice3->invoiceItems->first();

    expect($item1->paid_amount)->toBe(40000) // Fully paid
        ->and($item1->balance_amount)->toBe(0)
        ->and($item2->paid_amount)->toBe(10000) // Partially paid (50000 - 40000 = 10000)
        ->and($item2->balance_amount)->toBe(20000)
        ->and($item3->paid_amount)->toBe(0); // Untouched
});

it('handles payment for single invoice backward compatibility', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 25000,
        'due_at' => now()->subDays(3),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'child_id' => $this->child->id,
        'total' => 25000,
        'paid_amount' => 0,
        'balance_amount' => 25000,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'amount' => 25000,
        'status' => PaymentStatus::PAID,
    ]);

    $result = $this->action->execute($payment, collect([$invoice]), 25000);

    expect($result['total_invoices'])->toBe(1)
        ->and($result['fully_paid_count'])->toBe(1);

    $pivotRecord = $payment->invoices()->first()->pivot;
    expect($pivotRecord->amount)->toBe(25000);
});

it('skips invoices with zero balance', function () {
    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 30000,
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::PAID, // Already paid
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total' => 20000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'child_id' => $this->child->id, 'total' => 30000, 'paid_amount' => 30000, 'balance_amount' => 0, 'paid' => true]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'child_id' => $this->child->id, 'total' => 20000, 'paid_amount' => 0, 'balance_amount' => 20000]);

    // Mock getRemainingBalance to return 0 for invoice1
    $invoice1Wrapped = \Mockery::mock($invoice1)->makePartial();
    $invoice1Wrapped->shouldReceive('getRemainingBalance')->andReturn(0);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'amount' => 20000,
        'status' => PaymentStatus::PAID,
    ]);

    $result = $this->action->execute($payment, collect([$invoice1Wrapped, $invoice2]), 20000);

    // Should only allocate to invoice2
    expect($result['total_invoices'])->toBe(1)
        ->and($result['fully_paid_count'])->toBe(1);
});
