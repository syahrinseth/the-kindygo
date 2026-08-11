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
});

// ──────────────────────────────────────────────
// Core Invoice Migration
// ──────────────────────────────────────────────

it('migrates legacy invoices to invoices table', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('invoices')->count())->toBe(3);

    $invoice = DB::table('invoices')->where('id', 1)->first();
    expect($invoice->tenant_id)->toBe(1);
    expect($invoice->user_id)->toBe(1);
    expect($invoice->centre_id)->toBe(1);
});

it('preserves legacy invoice IDs', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    for ($i = 1; $i <= 3; $i++) {
        expect(DB::table('invoices')->where('id', $i)->exists())->toBeTrue();
    }
});

it('replaces spaces with hyphens in invoice numbers', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Seeded invoice_no: "INV 2025-1" → "INV-2025-1"
    $invoice = DB::table('invoices')->where('id', 1)->first();
    expect($invoice->number)->toBe('INV-2025-1');
});

it('generates fallback number for null invoice_no', function () {
    DB::connection('legacy')->table('1_invoices')->insert([
        'id' => 10,
        'parent' => 1,
        'preschool' => 1,
        'invoice_no' => null,
        'invoice_date' => '2025-01-01',
        'payment_status' => 1,
        'price' => 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 10)->first();
    expect($invoice->number)->toBe('LEGACY-10');
});

it('handles duplicate invoice numbers with DUP suffix', function () {
    $invoices = [];
    foreach (range(1, 25) as $id) {
        $invoices[] = [
            'id' => $id, 'parent' => 1, 'preschool' => 1,
            'invoice_no' => 'INV-001', 'invoice_date' => '2025-01-01',
            'payment_status' => 1, 'price' => 10000,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    DB::connection('legacy')->table('1_invoices')->insert($invoices);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $numbers = DB::table('invoices')->pluck('number')->toArray();
    expect($numbers)->toContain('INV-001');
    expect($numbers)->toContain('INV-001-DUP2');
    expect($numbers)->toContain('INV-001-DUP25');
});

it('maps invoice statuses correctly', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Statuses seeded: 1=pending, 7=paid, 5=cancelled
    $invoice1 = DB::table('invoices')->where('id', 1)->first();
    $invoice2 = DB::table('invoices')->where('id', 2)->first();
    $invoice3 = DB::table('invoices')->where('id', 3)->first();

    expect($invoice1->status)->toBe('pending');
    expect($invoice2->status)->toBe('paid');
    expect($invoice3->status)->toBe('cancelled');
});

it('skips soft-deleted invoices', function () {
    $this->seedLegacyInvoices(2, parentId: 1, preschoolId: 1);

    DB::connection('legacy')->table('1_invoices')->insert([
        'id' => 99, 'parent' => 1, 'preschool' => 1,
        'invoice_no' => 'DEL-001', 'invoice_date' => '2025-01-01',
        'payment_status' => 1, 'price' => 10000,
        'created_at' => now(), 'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('invoices')->where('id', 99)->exists())->toBeFalse();
});

it('sets user_id to null when parent is invalid', function () {
    DB::connection('legacy')->table('1_invoices')->insert([
        'id' => 10, 'parent' => 999, 'preschool' => 1,
        'invoice_no' => 'ORPHAN-001', 'invoice_date' => '2025-01-01',
        'payment_status' => 1, 'price' => 10000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 10)->first();
    expect($invoice->user_id)->toBeNull();
    expect(DB::table('migration_orphans')
        ->where('source_table', '1_invoices')
        ->where('source_id', 10)
        ->exists()
    )->toBeTrue();
});

it('sets centre_id to null when preschool is invalid', function () {
    DB::connection('legacy')->table('1_invoices')->insert([
        'id' => 10, 'parent' => 1, 'preschool' => 999,
        'invoice_no' => 'NO-CENTRE-001', 'invoice_date' => '2025-01-01',
        'payment_status' => 1, 'price' => 10000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 10)->first();
    expect($invoice->centre_id)->toBeNull();
});

// ──────────────────────────────────────────────
// Invoice Items (Bill Transactions)
// ──────────────────────────────────────────────

it('migrates bill transactions to invoice_items', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('invoice_items')->count())->toBe(3);

    $item = DB::table('invoice_items')->where('id', 1)->first();
    expect($item->invoice_id)->toBe(1);
    expect($item->name)->toBe('Bill Item 1');
    expect($item->description)->toBe('Description for bill item 1');
});

it('migrates deposit transactions as product invoice items', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyDeposits(startId: 200, count: 1, invoiceId: 1, parentId: 1, preschoolId: 1, amount: -5000);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $item = DB::table('invoice_items')->where('id', 200)->first();

    expect($item)->not->toBeNull();
    expect($item->type)->toBe('product');
    expect($item->price)->toBe(-5000);
    expect($item->total)->toBe(-5000);
});

it('calculates item amounts correctly', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(1, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Seeded: amount=15000, quantity=1, discount_amount=0
    // total = (15000 * 1) - (0 * 1) = 15000
    $item = DB::table('invoice_items')->where('id', 1)->first();
    expect($item->price)->toBe(15000);
    expect($item->quantity)->toBe(1);
    expect($item->discount)->toBe(0);
    expect($item->total)->toBe(15000);
});

it('handles discount items correctly', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);

    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 10,
        'invoice_id' => 1,
        'child_id' => 1,
        'product_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'bill',
        'label' => 'Discount Item',
        'remarks' => 'Monthly discount',
        'amount' => -5000,
        'quantity' => 1,
        'discount_amount' => 0,
        'is_discount' => 1,
        'bill_date' => '2025-01-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $item = DB::table('invoice_items')->where('id', 10)->first();
    expect($item->type)->toBe('invoice_discount');
});

it('sets item type to invoice_discount for negative prices', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);

    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 10,
        'invoice_id' => 1,
        'child_id' => 1,
        'product_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'bill',
        'label' => 'Credit Note',
        'amount' => -3000,
        'quantity' => 1,
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $item = DB::table('invoice_items')->where('id', 10)->first();
    expect($item->type)->toBe('invoice_discount');
});

it('looks up child_enrolment_id from child and product', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(1, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $item = DB::table('invoice_items')->where('id', 1)->first();
    $enrolment = DB::table('child_enrolments')->where('child_id', 1)->where('product_id', 1)->first();

    expect($item->child_enrolment_id)->toBe($enrolment->id);
});

it('uses invoice period dates for item period_start and period_end', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(1, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $item = DB::table('invoice_items')->where('id', 1)->first();
    // Seeded invoice start_date='2025-01-01', end_date='2025-01-31'
    expect($item->period_start)->toBe('2025-01-01');
    expect($item->period_end)->toBe('2025-01-31');
});

it('sets effective_date from bill_date', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(1, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $item = DB::table('invoice_items')->where('id', 1)->first();
    expect($item->effective_date)->toBe('2025-01-15');
});

it('skips bill items with invalid invoice_id', function () {
    // Don't seed any invoices
    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 10,
        'invoice_id' => 999,
        'child_id' => 1,
        'product_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'bill',
        'label' => 'Orphan Item',
        'amount' => 5000,
        'quantity' => 1,
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('invoice_items')->where('id', 10)->exists())->toBeFalse();
    expect(DB::table('migration_orphans')
        ->where('source_table', '1_transactions')
        ->where('source_id', 10)
        ->exists()
    )->toBeTrue();
});

it('skips soft-deleted bill transactions', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);

    DB::connection('legacy')->table('1_transactions')->insert([
        'id' => 10,
        'invoice_id' => 1,
        'child_id' => 1,
        'product_id' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'type' => 'bill',
        'label' => 'Deleted Item',
        'amount' => 5000,
        'quantity' => 1,
        'discount_amount' => 0,
        'is_discount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('invoice_items')->where('id', 10)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// Invoice Total Recalculation
// ──────────────────────────────────────────────

it('recalculates invoice totals from migrated items', function () {
    $this->seedLegacyInvoices(1, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoice = DB::table('invoices')->where('id', 1)->first();

    // Bills seeded with amounts: 15000, 20000, 25000
    expect($invoice->total_items)->toBe(3);
    expect($invoice->total)->toBe(60000); // sum of items
    expect($invoice->total_discounts)->toBe(0);
});

// ──────────────────────────────────────────────
// Idempotency & Modes
// ──────────────────────────────────────────────

it('is idempotent - re-running does not create duplicates', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoiceCount1 = DB::table('invoices')->count();
    $itemCount1 = DB::table('invoice_items')->count();

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('invoices')->count())->toBe($invoiceCount1);
    expect(DB::table('invoice_items')->count())->toBe($itemCount1);
});

it('does not make changes in dry-run mode', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--dry-run' => true])
        ->assertSuccessful();

    expect(DB::table('invoices')->count())->toBe(0);
    expect(DB::table('invoice_items')->count())->toBe(0);
});

it('supports start-id option to resume migration', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1, '--start-id' => 2])
        ->assertSuccessful();

    // Only invoices with id >= 2 should be migrated
    expect(DB::table('invoices')->where('id', 1)->exists())->toBeFalse();
    expect(DB::table('invoices')->where('id', 2)->exists())->toBeTrue();
    expect(DB::table('invoices')->where('id', 3)->exists())->toBeTrue();
});

it('supports bounded header and item process ranges', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', [
        '--tenant-id' => 1,
        '--end-id' => 2,
        '--skip-items' => true,
        '--skip-recalculation' => true,
    ])->assertSuccessful();

    expect(DB::table('invoices')->whereIn('id', [1, 2])->count())->toBe(2)
        ->and(DB::table('invoices')->where('id', 3)->exists())->toBeFalse()
        ->and(DB::table('invoice_items')->count())->toBe(0);

    $this->artisan('migrate:legacy-invoices', [
        '--tenant-id' => 1,
        '--skip-invoices' => true,
        '--items-end-id' => 2,
        '--skip-recalculation' => true,
    ])->assertSuccessful();

    expect(DB::table('invoice_items')->whereIn('id', [1, 2])->count())->toBe(2)
        ->and(DB::table('invoice_items')->where('id', 3)->exists())->toBeFalse();
});

it('supports items-start-id option to resume invoice item migration', function () {
    $this->seedLegacyInvoices(3, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(3, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1, '--items-start-id' => 2])
        ->assertSuccessful();

    expect(DB::table('invoice_items')->where('id', 1)->exists())->toBeFalse();
    expect(DB::table('invoice_items')->where('id', 2)->exists())->toBeTrue();
    expect(DB::table('invoice_items')->where('id', 3)->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Migration Logs
// ──────────────────────────────────────────────

it('creates migration log entries for invoices and items', function () {
    $this->seedLegacyInvoices(2, parentId: 1, preschoolId: 1);
    $this->seedLegacyBills(2, invoiceId: 1, childId: 1, productId: 1, parentId: 1, preschoolId: 1);

    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1])
        ->assertSuccessful();

    $invoiceLog = DB::table('migration_logs')
        ->where('phase', 'phase_3a_invoices')
        ->where('source_table', '1_invoices')
        ->first();

    expect($invoiceLog)->not->toBeNull();
    expect($invoiceLog->total_migrated)->toBe(2);

    $itemLog = DB::table('migration_logs')
        ->where('phase', 'phase_3a_items')
        ->where('source_table', '1_transactions')
        ->first();

    expect($itemLog)->not->toBeNull();
    expect($itemLog->total_migrated)->toBe(2);
});
