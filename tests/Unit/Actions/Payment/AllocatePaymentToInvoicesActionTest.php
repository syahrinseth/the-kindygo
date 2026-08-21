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
use Illuminate\Support\Facades\DB;

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
        'total_amount' => 50000, // RM 500
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

    // Note: Invoice status is NOT updated by AllocatePaymentToInvoicesAction
    // Status updates are handled by ProcessPaymentAllocationAction
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::OVERDUE); // Status remains unchanged

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
        'total_amount' => 30000, // RM 300
        'due_at' => now()->subDays(10), // Oldest
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 20000, // RM 200
        'due_at' => now()->subDays(5), // Second oldest
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice3 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 25000, // RM 250
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
        'total_amount' => 40000, // RM 400
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::OVERDUE,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 30000, // RM 300
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice3 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 20000, // RM 200
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

    // Note: Invoice status is NOT updated by AllocatePaymentToInvoicesAction
    // Status updates are handled by ProcessPaymentAllocationAction
    expect($invoice1->status)->toBe(InvoiceStatus::OVERDUE) // Status remains unchanged
        ->and($invoice2->status)->toBe(InvoiceStatus::OVERDUE) // Remains overdue
        ->and($invoice3->status)->toBe(InvoiceStatus::OVERDUE); // Remains overdue

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
        'total_amount' => 25000,
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
        'total_amount' => 30000,
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::PAID, // Already paid
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 20000,
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

it('handles pre-attached invoices with zero amount gracefully', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 50000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
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
        'status' => PaymentStatus::PAID, // Must be PAID for getTotalPaid() to count it
    ]);

    // PRE-ATTACH invoice with amount = 0 (simulating CreateChipPaymentAction behavior)
    $payment->invoices()->attach($invoice->id, ['amount' => 0]);

    // Verify invoice is pre-attached with amount = 0
    $preAttachedAmount = $payment->invoices()->first()->pivot->amount;
    expect($preAttachedAmount)->toBe(0);

    // Now run the allocation action (should update, not fail with duplicate entry)
    $result = $this->action->execute($payment, collect([$invoice]), 50000);

    expect($result['fully_paid_count'])->toBe(1)
        ->and($result['partially_paid_count'])->toBe(0)
        ->and($result['total_invoices'])->toBe(1);

    // Verify pivot amount was updated from 0 to 50000
    $payment->refresh();
    $pivotAmount = $payment->invoices()->first()->pivot->amount;
    expect($pivotAmount)->toBe(50000);

    // Verify invoice item was updated
    $invoice->refresh();
    $item = $invoice->invoiceItems->first();
    expect($item->paid_amount)->toBe(50000)
        ->and($item->balance_amount)->toBe(0)
        ->and($item->paid)->toBeTrue();
});

it('is idempotent when processing same payment allocation twice', function () {
    $invoice = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 30000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'child_id' => $this->child->id,
        'name' => 'Test Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'amount' => 30000,
        'status' => PaymentStatus::PAID,
    ]);

    // First execution
    $result1 = $this->action->execute($payment, collect([$invoice]), 30000);

    expect($result1['fully_paid_count'])->toBe(1)
        ->and($result1['total_invoices'])->toBe(1);

    // Verify first execution results
    $pivotCount1 = DB::table('invoice_payment')
        ->where('payment_id', $payment->id)
        ->where('invoice_id', $invoice->id)
        ->count();
    expect($pivotCount1)->toBe(1);

    $item = $invoice->fresh()->invoiceItems->first();
    $firstPaidAmount = $item->paid_amount;
    expect($firstPaidAmount)->toBe(30000);

    // Second execution (idempotent - should not fail or double-allocate)
    // Note: Since invoice is already fully paid from first run, it will be skipped
    $result2 = $this->action->execute($payment, collect([$invoice]), 30000);

    // On second run, invoice is already paid, so nothing to allocate
    expect($result2['fully_paid_count'])->toBe(0)
        ->and($result2['total_invoices'])->toBe(0);

    // Verify only ONE pivot record exists (not duplicated)
    $pivotCount2 = DB::table('invoice_payment')
        ->where('payment_id', $payment->id)
        ->where('invoice_id', $invoice->id)
        ->count();
    expect($pivotCount2)->toBe(1);

    // Verify pivot amount is correct (not doubled)
    $payment->refresh();
    $pivotAmount = $payment->invoices()->first()->pivot->amount;
    expect($pivotAmount)->toBe(30000);

    // Verify invoice item is correct (paid amount should still be 30000, not 60000)
    $item->refresh();
    expect($item->paid_amount)->toBe(30000)
        ->and($item->balance_amount)->toBe(0)
        ->and($item->paid)->toBeTrue();
});

it('handles multi-centre payment allocation with pre-attached invoices', function () {
    $centre2 = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'user_id' => $this->user->id,
        'total_amount' => 30000,
        'due_at' => now()->subDays(10),
        'status' => InvoiceStatus::PENDING,
    ]);

    $invoice2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre2->id,
        'user_id' => $this->user->id,
        'total_amount' => 20000,
        'due_at' => now()->subDays(5),
        'status' => InvoiceStatus::PENDING,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice1->id,
        'child_id' => $this->child->id,
        'name' => 'Centre 1 Item',
        'price' => 30000,
        'quantity' => 1,
        'discount' => 0,
        'total' => 30000,
        'paid_amount' => 0,
        'balance_amount' => 30000,
        'paid' => false,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice2->id,
        'child_id' => $this->child->id,
        'name' => 'Centre 2 Item',
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
        'amount' => 50000,
        'status' => PaymentStatus::PAID, // Must be PAID for getTotalPaid() to count it
    ]);

    // PRE-ATTACH both invoices with amount = 0 (CHIP flow)
    $payment->invoices()->attach([
        $invoice1->id => ['amount' => 0],
        $invoice2->id => ['amount' => 0],
    ]);

    // User-defined allocation
    $userAllocation = [
        $invoice1->id => 30000,
        $invoice2->id => 20000,
    ];

    // Now process allocation (should update existing pivots, not create duplicates)
    $result = $this->action->execute($payment, collect([$invoice1, $invoice2]), 50000, $userAllocation);

    expect($result['fully_paid_count'])->toBe(2)
        ->and($result['partially_paid_count'])->toBe(0)
        ->and($result['total_invoices'])->toBe(2);

    // Verify pivot amounts were updated correctly
    $payment->refresh();
    $invoice1Pivot = $payment->invoices()->where('invoice_id', $invoice1->id)->first()->pivot;
    $invoice2Pivot = $payment->invoices()->where('invoice_id', $invoice2->id)->first()->pivot;

    expect($invoice1Pivot->amount)->toBe(30000)
        ->and($invoice2Pivot->amount)->toBe(20000);

    // Verify only 2 pivot records exist (one per invoice, not duplicated)
    $totalPivotCount = DB::table('invoice_payment')
        ->where('payment_id', $payment->id)
        ->count();
    expect($totalPivotCount)->toBe(2);
});
