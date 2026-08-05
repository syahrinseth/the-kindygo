<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();

    // Set up all prerequisites in order
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(2, 1);
    $this->seedLegacyRoles();
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);

    $this->seedLegacyProducts(3, [1]);
    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1]);

    $this->seedLegacyUsers(3, 1);
    $this->seedLegacyModelHasRoles([
        ['role_id' => 7, 'model_id' => 1],
        ['role_id' => 7, 'model_id' => 2],
        ['role_id' => 7, 'model_id' => 3],
    ]);
    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1]);

    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);
    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1]);

    // Seed and migrate invoices (required for payment pivot)
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);
    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1]);
});

// ──────────────────────────────────────────────
// Core Payment Migration
// ──────────────────────────────────────────────

it('migrates legacy payment transactions to payments table', function () {
    $this->seedLegacyPayments(startId: 100, count: 2, invoiceId: 1, parentId: 1, preschoolId: 1, amount: 15000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->count())->toBe(2);

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->tenant_id)->toBe(1);
    expect($payment->user_id)->toBe(1);
    expect($payment->amount)->toBe(15000);
    expect($payment->description)->toBe('Payment 100');
});

it('does not migrate legacy deposit transactions to payments or invoice_payment', function () {
    $this->seedLegacyDeposits(startId: 200, count: 1, invoiceId: 1, parentId: 1, preschoolId: 1, amount: 5000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->where('id', 200)->exists())->toBeFalse();
    expect(DB::table('invoice_payment')->where('payment_id', 200)->exists())->toBeFalse();
});

it('preserves legacy transaction IDs as payment IDs', function () {
    $this->seedLegacyPayments(startId: 100, count: 3, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->where('id', 100)->exists())->toBeTrue();
    expect(DB::table('payments')->where('id', 101)->exists())->toBeTrue();
    expect(DB::table('payments')->where('id', 102)->exists())->toBeTrue();
});

it('uses paid_amount for payment transactions', function () {
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1, amount: 25000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->amount)->toBe(25000);
});

it('reconciles previously migrated deposit payments and their pivots', function () {
    $this->seedLegacyDeposits(startId: 200, count: 1, invoiceId: 1, parentId: 1, amount: 8000);

    DB::table('payments')->insert([
        'id' => 200,
        'tenant_id' => 1,
        'user_id' => 1,
        'gateway' => 'bank_transfer',
        'reference_no' => 'DEP-200',
        'status' => 'paid',
        'amount' => 8000,
        'meta' => json_encode(['legacy_type' => 'deposit']),
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('invoice_payment')->insert([
        'payment_id' => 200,
        'invoice_id' => 1,
        'amount' => 8000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();
    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->where('id', 200)->exists())->toBeFalse();
    expect(DB::table('invoice_payment')->where('payment_id', 200)->exists())->toBeFalse();
    expect(DB::table('invoice_items')->where('id', 200)->where('type', 'product')->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Gateway Mapping
// ──────────────────────────────────────────────

it('maps payment_method to bank_transfer gateway by default', function () {
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Seeded payment_method = 2 → BANK_TRANSFER
    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->gateway)->toBe('bank_transfer');
});

it('maps billplz_bill_id to billplz gateway', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Billplz Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 1,
        'paid_status' => 1,
        'paid_amount' => 20000,
        'paid_at' => now()->toDateTimeString(),
        'billplz_bill_id' => 'bp-12345',
        'billplz_collection_id' => 'coll-001',
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->gateway)->toBe('billplz');
    expect($payment->gateway_payment_id)->toBe('bp-12345');

    $gatewayData = json_decode($payment->gateway_payment_data, true);
    expect($gatewayData['billplz_bill_id'])->toBe('bp-12345');
    expect($gatewayData['billplz_collection_id'])->toBe('coll-001');
});

it('maps payment_method 4 and 9 to chip gateway', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'CHIP Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 4,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => now()->toDateTimeString(),
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->gateway)->toBe('chip');
});

// ──────────────────────────────────────────────
// Payment Status
// ──────────────────────────────────────────────

it('maps paid_status to payment status correctly', function () {
    // paid_status 1 → PAID
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->status)->toBe('paid');
});

it('maps paid_status 0 to unpaid', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Unpaid Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 0,
        'paid_amount' => 15000,
        'paid_at' => null,
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->status)->toBe('unpaid');
});

// ──────────────────────────────────────────────
// Orphan Handling
// ──────────────────────────────────────────────

it('skips payments with invalid parent_id (user FK)', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 999,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Orphan Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => now()->toDateTimeString(),
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->where('id', 100)->exists())->toBeFalse();
    expect(DB::table('migration_orphans')
        ->where('source_table', '1_transactions')
        ->where('source_id', 100)
        ->exists()
    )->toBeTrue();
});

it('skips soft-deleted payment transactions', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Deleted Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => now()->toDateTimeString(),
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->where('id', 100)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// Meta Data
// ──────────────────────────────────────────────

it('stores legacy metadata in meta JSON field', function () {
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    $meta = json_decode($payment->meta, true);

    expect($meta['legacy']['transaction_type'])->toBe('payment');
    expect($meta['legacy_label'])->toBe('Payment 100');
    expect($meta['payment_by'])->toBe('Online');
    expect($meta['legacy']['payment_slip_path'])->toContain('payment_slips/100.jpg');
});

it('stores reference_no from legacy reference_id', function () {
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->reference_no)->toBe('REF-100');
});

// ──────────────────────────────────────────────
// Date Sanitisation
// ──────────────────────────────────────────────

it('sanitises malformed dates with 2-digit years', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Bad Date Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => '0020-12-30 10:00:00',
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->paid_at)->toContain('2020-12-30');
});

it('rejects dates before year 2000', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Epoch Payment',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => '1970-01-01 00:00:00',
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $payment = DB::table('payments')->where('id', 100)->first();
    expect($payment->paid_at)->toBeNull();
});

// ──────────────────────────────────────────────
// Invoice-Payment Pivot
// ──────────────────────────────────────────────

it('creates invoice_payment pivot entries', function () {
    $this->seedLegacyPayments(startId: 100, count: 2, invoiceId: 1, parentId: 1, amount: 15000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $pivots = DB::table('invoice_payment')->where('invoice_id', 1)->get();
    expect($pivots)->toHaveCount(2);

    $pivot = $pivots->first();
    expect($pivot->amount)->toBe(15000);
});

it('skips pivot when payment was skipped due to orphan user', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 1,
        'parent_id' => 999,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Orphan Pivot',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => now()->toDateTimeString(),
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Payment was skipped, so pivot should also be skipped
    expect(DB::table('invoice_payment')->where('payment_id', 100)->exists())->toBeFalse();
});

it('skips pivot when invoice_id is invalid', function () {
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 100,
        'invoice_id' => 999,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'payment',
        'label' => 'Invalid Invoice Pivot',
        'amount' => 0,
        'quantity' => 1,
        'payment_method' => 2,
        'paid_status' => 1,
        'paid_amount' => 15000,
        'paid_at' => now()->toDateTimeString(),
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Payment migrated but pivot skipped because invoice 999 doesn't exist
    expect(DB::table('payments')->where('id', 100)->exists())->toBeTrue();
    expect(DB::table('invoice_payment')->where('payment_id', 100)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// Invoice Status Updates
// ──────────────────────────────────────────────

it('updates invoice status to PAID when payments cover total', function () {
    // Invoice 1 has total recalculated from bill items (15000+20000+25000 = 60000)
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1, amount: 60000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 1)->first();
    expect($invoice->status)->toBe('paid');
});

it('updates invoice status to PARTIALLY_PAID when payments are partial', function () {
    // Invoice 1 total = 60000, pay only 30000
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1, amount: 30000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 1)->first();
    expect($invoice->status)->toBe('partially_paid');
});

it('does not override cancelled invoice status from payment updates', function () {
    // Invoice 3 was seeded with payment_status=5 → cancelled
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 3, parentId: 1, amount: 90000);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 3)->first();
    expect($invoice->status)->toBe('cancelled'); // Should remain cancelled
});

// ──────────────────────────────────────────────
// Idempotency & Modes
// ──────────────────────────────────────────────

it('is idempotent - re-running does not create duplicates', function () {
    $this->seedLegacyPayments(startId: 100, count: 2, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $paymentCount1 = DB::table('payments')->count();
    $pivotCount1 = DB::table('invoice_payment')->count();

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('payments')->count())->toBe($paymentCount1);
    expect(DB::table('invoice_payment')->count())->toBe($pivotCount1);
});

it('does not make changes in dry-run mode', function () {
    $this->seedLegacyPayments(startId: 100, count: 2, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--dry-run' => true])
        ->assertSuccessful();

    expect(DB::table('payments')->count())->toBe(0);
    expect(DB::table('invoice_payment')->count())->toBe(0);
});

it('supports start-id option to resume migration', function () {
    $this->seedLegacyPayments(startId: 100, count: 3, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1, '--start-id' => 102])
        ->assertSuccessful();

    expect(DB::table('payments')->where('id', 100)->exists())->toBeFalse();
    expect(DB::table('payments')->where('id', 101)->exists())->toBeFalse();
    expect(DB::table('payments')->where('id', 102)->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Migration Logs
// ──────────────────────────────────────────────

it('creates migration log entries for payments', function () {
    $this->seedLegacyPayments(startId: 100, count: 2, invoiceId: 1, parentId: 1);

    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1])
        ->assertSuccessful();

    $paymentLog = DB::table('migration_logs')
        ->where('phase', 'phase_3b_payments')
        ->where('source_table', '1_transactions')
        ->first();

    expect($paymentLog)->not->toBeNull();
    expect($paymentLog->total_migrated)->toBe(2);
    expect($paymentLog->completed_at)->not->toBeNull();

    $pivotLog = DB::table('migration_logs')
        ->where('phase', 'phase_3b_pivot')
        ->first();

    expect($pivotLog)->not->toBeNull();
});
