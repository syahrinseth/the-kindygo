<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();
});

/**
 * Helper: run a full migration pipeline so validation has data to check.
 *
 * @param  array<string, mixed>  $legacyUserOverrides
 */
function runFullMigration(array $legacyUserOverrides = []): void
{
    test()->seedLegacyCampuses(1);
    test()->seedLegacyPreschools(2, 1);
    test()->seedLegacyRoles();
    test()->seedLegacyUsers(3, 1);
    if ($legacyUserOverrides !== []) {
        DB::connection('legacy')->table('1_users')->where('id', 1)->update($legacyUserOverrides);
    }
    test()->seedLegacyProducts(2, [1]);
    test()->seedLegacyChildren(2, 1, 1, 1);
    test()->seedLegacyInvoices(2, 1, 1);
    test()->seedLegacyBills(2, 1, 1, 1, 1, 1);
    test()->seedLegacyDeposits(startId: 200, count: 1, invoiceId: 1, parentId: 1, preschoolId: 1, amount: 5000);
    test()->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1, preschoolId: 1, amount: 15000);

    test()->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
    test()->artisan('migrate:legacy-roles');
    test()->artisan('migrate:legacy-users', ['--tenant-id' => 1]);
    test()->artisan('migrate:legacy-products', ['--tenant-id' => 1]);
    test()->artisan('migrate:legacy-children', ['--tenant-id' => 1]);
    test()->artisan('migrate:legacy-invoices', ['--tenant-id' => 1]);
    test()->artisan('migrate:legacy-payments', ['--tenant-id' => 1]);
}

// ──────────────────────────────────────────────
// Overall Command Behaviour
// ──────────────────────────────────────────────

it('returns success when all validation checks pass', function () {
    runFullMigration();

    $this->artisan('migrate:legacy-validate', ['--tenant-id' => 1])
        ->assertSuccessful();
});

it('returns failure when foreign key integrity fails', function () {
    runFullMigration();

    // SQLite enforces FK constraints even inside transactions, so we cannot directly
    // insert/update to create dangling FK references. Instead, we create a copy of the
    // invoices table WITHOUT FK constraints, insert the bad data there, then swap.
    DB::statement('CREATE TABLE invoices_temp AS SELECT * FROM invoices WHERE 1=0');
    // Copy all existing invoices
    DB::statement('INSERT INTO invoices_temp SELECT * FROM invoices');
    // Insert a dangling reference (user_id=99999 does not exist)
    DB::statement("INSERT INTO invoices_temp (id, number, tenant_id, user_id, date, status, total_items, subtotal_amount, discount_amount, total_amount, created_at, updated_at)
        VALUES (999, 'INV-FK-TEST', 1, 99999, datetime('now'), 'pending', 0, 0, 0, 0, datetime('now'), datetime('now'))");
    // Replace the original table
    DB::statement('DROP TABLE invoices');
    DB::statement('ALTER TABLE invoices_temp RENAME TO invoices');

    // Verify the dangling reference exists
    $dangling = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = 1 AND user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)'
    )->cnt;
    expect($dangling)->toBe(1);

    $this->artisan('migrate:legacy-validate', ['--tenant-id' => 1])
        ->assertFailed();
});

// ──────────────────────────────────────────────
// Section 1: Record Count Validation
// ──────────────────────────────────────────────

it('validates record counts between legacy and current database', function () {
    runFullMigration();

    // After a clean migration, counts should match
    $legacyCentres = DB::connection('legacy')
        ->table('1_preschool')
        ->whereNull('deleted_at')
        ->count();
    $currentCentres = DB::table('centres')
        ->where('tenant_id', 1)
        ->count();

    expect($currentCentres)->toBe($legacyCentres);
});

it('detects count mismatches when records are missing', function () {
    runFullMigration();

    // Delete a migrated centre to create a mismatch
    $lastCentre = DB::table('centres')->where('tenant_id', 1)->orderByDesc('id')->first();
    if ($lastCentre) {
        DB::table('centres')->where('id', $lastCentre->id)->delete();
    }

    // The validate command reports warnings for count mismatches but doesn't fail
    // (count mismatches track as WARN, not FAIL)
    $this->artisan('migrate:legacy-validate', ['--tenant-id' => 1])
        ->assertSuccessful(); // WARNs don't cause failure
});

// ──────────────────────────────────────────────
// Section 2: Foreign Key Integrity
// ──────────────────────────────────────────────

it('passes FK integrity check when all references are valid', function () {
    runFullMigration();

    // All FK references should be valid after clean migration
    $danglingInvoiceUsers = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = 1 AND user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)'
    )->cnt;

    expect($danglingInvoiceUsers)->toBe(0);
});

it('detects dangling payment-user references', function () {
    runFullMigration();

    // SQLite enforces FK constraints inside transactions, so we recreate the payments
    // table without FK constraints to insert a dangling user reference.
    DB::statement('CREATE TABLE payments_temp AS SELECT * FROM payments WHERE 1=0');
    DB::statement('INSERT INTO payments_temp SELECT * FROM payments');
    DB::statement("INSERT INTO payments_temp (id, tenant_id, user_id, gateway, status, amount, created_at, updated_at)
        VALUES (9999, 1, 88888, 'bank_transfer', 'paid', 10000, datetime('now'), datetime('now'))");
    DB::statement('DROP TABLE payments');
    DB::statement('ALTER TABLE payments_temp RENAME TO payments');

    $danglingPaymentUsers = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM payments WHERE tenant_id = 1 AND user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)'
    )->cnt;

    expect($danglingPaymentUsers)->toBe(1);

    // Command should FAIL because FK integrity is tracked as FAIL not WARN
    $this->artisan('migrate:legacy-validate', ['--tenant-id' => 1])
        ->assertFailed();
});

it('detects dangling invoice-payment pivot references', function () {
    runFullMigration();

    // SQLite enforces FK constraints inside transactions, so we recreate the
    // invoice_payment table without FK constraints to insert a dangling reference.
    DB::statement('CREATE TABLE invoice_payment_temp AS SELECT * FROM invoice_payment WHERE 1=0');
    DB::statement('INSERT INTO invoice_payment_temp SELECT * FROM invoice_payment');
    DB::statement("INSERT INTO invoice_payment_temp (payment_id, invoice_id, amount, created_at, updated_at)
        VALUES (77777, 1, 5000, datetime('now'), datetime('now'))");
    DB::statement('DROP TABLE invoice_payment');
    DB::statement('ALTER TABLE invoice_payment_temp RENAME TO invoice_payment');

    $danglingPivot = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM invoice_payment WHERE payment_id NOT IN (SELECT id FROM payments)'
    )->cnt;

    expect($danglingPivot)->toBe(1);
});

// ──────────────────────────────────────────────
// Section 3: Financial Data Integrity
// ──────────────────────────────────────────────

it('validates invoice totals match sum of items', function () {
    runFullMigration();

    // After migration with recalculated totals, they should match
    $mismatched = DB::selectOne('
        SELECT COUNT(*) as cnt FROM invoices i
        WHERE i.tenant_id = 1
        AND i.total_amount != (
            SELECT COALESCE(SUM(ii.total), 0) FROM invoice_items ii WHERE ii.invoice_id = i.id
        )
    ')->cnt;

    expect($mismatched)->toBe(0);
});

it('detects payments with zero or negative amounts', function () {
    runFullMigration();

    // Insert a payment with zero amount
    DB::table('payments')->insert([
        'id' => 8888,
        'tenant_id' => 1,
        'user_id' => 1,
        'gateway' => 'bank_transfer',
        'status' => 'paid',
        'amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $zeroPayments = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM payments WHERE tenant_id = 1 AND amount <= 0'
    )->cnt;

    expect($zeroPayments)->toBe(1);
});

it('validates total payment amounts between legacy and current', function () {
    runFullMigration();

    $legacyPaymentTotal = DB::connection('legacy')->selectOne("
        SELECT COALESCE(SUM(paid_amount), 0) as total FROM `1_transactions`
        WHERE type = 'payment' AND deleted_at IS NULL
    ")->total;

    $currentTotal = DB::selectOne(
        'SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE tenant_id = 1'
    )->total;

    // Payment amounts should match between legacy and current
    expect((int) $currentTotal)->toBe((int) $legacyPaymentTotal);
});

// ──────────────────────────────────────────────
// Section 4: Orphan Records
// ──────────────────────────────────────────────

it('reports no orphans when migration is clean', function () {
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(1, 1);
    $this->seedLegacyRoles();
    $this->seedLegacyUsers(1, 1);
    $this->seedLegacyProducts(1, [1]);
    $this->seedLegacyChildren(1, 1, 1, 1);
    $this->seedLegacyInvoices(1, 1, 1);
    $this->seedLegacyBills(1, 1, 1, 1, 1, 1);
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1, preschoolId: 1, amount: 10000);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-roles');
    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-invoices', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-payments', ['--tenant-id' => 1]);

    // Clean migration should produce no orphans (all FKs valid)
    $orphanCount = DB::table('migration_orphans')->count();
    expect($orphanCount)->toBe(0);
});

it('reports orphan records when they exist', function () {
    runFullMigration();

    // Manually insert orphan record
    DB::table('migration_orphans')->insert([
        'source_table' => '1_child',
        'source_id' => 999,
        'reason' => 'Invalid preschool_id',
        'data' => json_encode(['preschool_id' => 999]),
        'created_at' => now(),
    ]);

    $orphans = DB::table('migration_orphans')
        ->select('source_table', 'reason', DB::raw('COUNT(*) as cnt'))
        ->groupBy('source_table', 'reason')
        ->get();

    expect($orphans)->not->toBeEmpty();
});

// ──────────────────────────────────────────────
// Section 5: Enum/Status Consistency
// ──────────────────────────────────────────────

it('validates all invoice statuses are valid enum values', function () {
    runFullMigration();

    $validStatuses = ['draft', 'pending', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'];

    $invoiceStatuses = DB::table('invoices')
        ->where('tenant_id', 1)
        ->select('status')
        ->distinct()
        ->pluck('status')
        ->toArray();

    foreach ($invoiceStatuses as $status) {
        expect($validStatuses)->toContain($status);
    }
});

it('validates all payment statuses are valid enum values', function () {
    runFullMigration();

    $validStatuses = ['pending', 'paid', 'unpaid', 'failed', 'cancelled', 'refunded', 'partially_paid'];

    $paymentStatuses = DB::table('payments')
        ->where('tenant_id', 1)
        ->select('status')
        ->distinct()
        ->pluck('status')
        ->toArray();

    foreach ($paymentStatuses as $status) {
        expect($validStatuses)->toContain($status);
    }
});

it('validates all payment gateways are valid enum values', function () {
    runFullMigration();

    $validGateways = ['bank_transfer', 'chip', 'billplz', 'stripe', 'cash'];

    $gateways = DB::table('payments')
        ->where('tenant_id', 1)
        ->select('gateway')
        ->distinct()
        ->pluck('gateway')
        ->toArray();

    foreach ($gateways as $gateway) {
        expect($validGateways)->toContain($gateway);
    }
});

it('detects invalid enum values inserted directly', function () {
    runFullMigration();

    // Force an invalid status directly
    DB::table('invoices')->insert([
        'id' => 7777,
        'number' => 'INV-BAD-STATUS',
        'tenant_id' => 1,
        'date' => now(),
        'status' => 'invalid_status',
        'total_items' => 0,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $validStatuses = ['draft', 'pending', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'];
    $allStatuses = DB::table('invoices')
        ->where('tenant_id', 1)
        ->pluck('status')
        ->toArray();

    $invalid = array_diff($allStatuses, $validStatuses);
    expect($invalid)->not->toBeEmpty();
    expect($invalid)->toContain('invalid_status');
});

it('fails validation when a migrated state value is not canonical', function () {
    runFullMigration();

    DB::table('user_addresses')
        ->where('user_id', 1)
        ->update(['state_code' => 'SGR']);

    $this->artisan('migrate:legacy-validate', ['--tenant-id' => 1])
        ->expectsOutputToContain('User address state codes')
        ->assertFailed();
});

it('passes state validation after canonical legacy migration mapping', function () {
    runFullMigration([
        'state' => 'SGR',
        'company_state' => 'WP KUALA LUMPUR',
        'spouse_state' => 'Selangor',
        'spouse_company_state' => 'KUL',
    ]);

    $validStateCodes = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16'];

    expect(DB::table('user_addresses')->whereNotNull('state_code')->whereNotIn('state_code', $validStateCodes)->count())->toBe(0)
        ->and(DB::table('user_office_infos')->whereNotNull('office_state_code')->whereNotIn('office_state_code', $validStateCodes)->count())->toBe(0)
        ->and(DB::table('family_members')->whereNotNull('state_code')->whereNotIn('state_code', $validStateCodes)->count())->toBe(0)
        ->and(DB::table('family_members')->whereNotNull('office_state_code')->whereNotIn('office_state_code', $validStateCodes)->count())->toBe(0);
});

// ──────────────────────────────────────────────
// Section 6: Media Attachments
// ──────────────────────────────────────────────

it('reports no media when Phase 4 has not been run', function () {
    runFullMigration();

    // Without running media migration, media table should be empty or absent
    $mediaCount = DB::table('media')->count();
    expect($mediaCount)->toBe(0);
});

// ──────────────────────────────────────────────
// Section 7: Migration Logs Summary
// ──────────────────────────────────────────────

it('shows migration log entries after full migration', function () {
    runFullMigration();

    $logs = DB::table('migration_logs')->get();
    expect($logs->count())->toBeGreaterThan(0);

    // Each phase should have logged entries
    $phases = $logs->pluck('phase')->unique()->toArray();
    expect($phases)->toContain('phase_1');
    expect($phases)->toContain('phase_2a');
    expect($phases)->toContain('phase_3a_invoices');
});

it('logs total_source and total_migrated counts', function () {
    runFullMigration();

    $centreLog = DB::table('migration_logs')
        ->where('source_table', '1_preschool')
        ->first();

    expect($centreLog)->not->toBeNull();
    expect($centreLog->total_source)->toBeGreaterThan(0);
    expect($centreLog->total_migrated)->toBeGreaterThan(0);
    expect($centreLog->completed_at)->not->toBeNull();
});

it('marks all migration log entries as completed', function () {
    runFullMigration();

    $incompleteLogs = DB::table('migration_logs')
        ->whereNull('completed_at')
        ->count();

    expect($incompleteLogs)->toBe(0);
});
