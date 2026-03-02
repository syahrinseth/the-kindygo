<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();

    // Seed all legacy data for orchestrator tests
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(2, 1);
    $this->seedLegacyRoles();
    $this->seedLegacyUsers(3, 1);
    $this->seedLegacyProducts(2, [1]);
    $this->seedLegacyChildren(2, 1, 1, 1);
    $this->seedLegacyInvoices(2, 1, 1);
    $this->seedLegacyBills(2, 1, 1, 1, 1, 1);
    $this->seedLegacyPayments(startId: 100, count: 1, invoiceId: 1, parentId: 1, preschoolId: 1, amount: 15000);
});

// ──────────────────────────────────────────────
// Full Orchestrator Tests
// ──────────────────────────────────────────────

it('runs all phases successfully end-to-end', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Verify data was migrated across all phases
    expect(DB::table('campuses')->count())->toBeGreaterThan(0);
    expect(DB::table('centres')->where('tenant_id', 1)->count())->toBe(2);
    expect(DB::table('users')->count())->toBeGreaterThanOrEqual(3);
    expect(DB::table('products')->where('tenant_id', 1)->count())->toBe(2);
    expect(DB::table('children')->count())->toBe(2);
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe(2);
    expect(DB::table('payments')->where('tenant_id', 1)->count())->toBeGreaterThanOrEqual(1);
});

it('runs all phases including validation', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Validation phase should have also run
    expect(DB::table('migration_logs')->count())->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────
// Phase Range Selection
// ──────────────────────────────────────────────

it('supports from-phase option to start at a specific phase', function () {
    // First run phases 1-2 manually
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-roles');
    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1]);

    // Then use orchestrator from phase 3 onwards
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--from-phase' => 3,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Phase 3 data should exist
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe(2);
    expect(DB::table('payments')->where('tenant_id', 1)->count())->toBeGreaterThanOrEqual(1);
});

it('supports to-phase option to stop at a specific phase', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--to-phase' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Only phase 1 should have run — centres and roles
    expect(DB::table('centres')->where('tenant_id', 1)->count())->toBe(2);

    // Phase 2 and 3 should NOT have run
    expect(DB::table('products')->where('tenant_id', 1)->count())->toBe(0);
    expect(DB::table('children')->count())->toBe(0);
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe(0);
});

it('supports combined from-phase and to-phase for a specific range', function () {
    // Run phase 1 first (dependency for phase 2)
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-roles');

    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--from-phase' => 2,
        '--to-phase' => 2,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Phase 2 data should exist
    expect(DB::table('users')->count())->toBeGreaterThanOrEqual(3);
    expect(DB::table('products')->where('tenant_id', 1)->count())->toBe(2);
    expect(DB::table('children')->count())->toBe(2);

    // Phase 3 should NOT have run
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe(0);
});

// ──────────────────────────────────────────────
// Dry Run Mode
// ──────────────────────────────────────────────

it('passes dry-run flag to all sub-commands', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--dry-run' => true,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Nothing should have been migrated
    expect(DB::table('campuses')->count())->toBe(0);
    expect(DB::table('centres')->where('tenant_id', 1)->count())->toBe(0);
    expect(DB::table('products')->where('tenant_id', 1)->count())->toBe(0);
    expect(DB::table('children')->count())->toBe(0);
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe(0);
    expect(DB::table('payments')->where('tenant_id', 1)->count())->toBe(0);
});

// ──────────────────────────────────────────────
// Skip Flags
// ──────────────────────────────────────────────

it('skips media migration when skip-media flag is set', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Media table should be empty (phase 4 skipped)
    expect(DB::table('media')->count())->toBe(0);
});

it('skips validation when skip-validation flag is set', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // All data phases should still work
    expect(DB::table('centres')->where('tenant_id', 1)->count())->toBe(2);
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe(2);
});

// ──────────────────────────────────────────────
// Idempotency
// ──────────────────────────────────────────────

it('is idempotent - re-running the full orchestrator produces same results', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $centreCount = DB::table('centres')->where('tenant_id', 1)->count();
    $userCount = DB::table('users')->count();
    $productCount = DB::table('products')->where('tenant_id', 1)->count();
    $childCount = DB::table('children')->count();
    $invoiceCount = DB::table('invoices')->where('tenant_id', 1)->count();
    $paymentCount = DB::table('payments')->where('tenant_id', 1)->count();

    // Run again
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(DB::table('centres')->where('tenant_id', 1)->count())->toBe($centreCount);
    expect(DB::table('users')->count())->toBe($userCount);
    expect(DB::table('products')->where('tenant_id', 1)->count())->toBe($productCount);
    expect(DB::table('children')->count())->toBe($childCount);
    expect(DB::table('invoices')->where('tenant_id', 1)->count())->toBe($invoiceCount);
    expect(DB::table('payments')->where('tenant_id', 1)->count())->toBe($paymentCount);
});

// ──────────────────────────────────────────────
// Data Integrity Across Phases
// ──────────────────────────────────────────────

it('maintains referential integrity across all migration phases', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // No dangling invoice → user references
    $danglingInvoiceUsers = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = 1 AND user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)'
    )->cnt;
    expect($danglingInvoiceUsers)->toBe(0);

    // No dangling invoice → centre references
    $danglingInvoiceCentres = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = 1 AND centre_id IS NOT NULL AND centre_id NOT IN (SELECT id FROM centres)'
    )->cnt;
    expect($danglingInvoiceCentres)->toBe(0);

    // No dangling child_enrolments → children references
    $danglingEnrolmentChildren = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM child_enrolments WHERE tenant_id = 1 AND child_id NOT IN (SELECT id FROM children)'
    )->cnt;
    expect($danglingEnrolmentChildren)->toBe(0);

    // No dangling child_enrolments → products references
    $danglingEnrolmentProducts = DB::selectOne(
        'SELECT COUNT(*) as cnt FROM child_enrolments WHERE tenant_id = 1 AND product_id NOT IN (SELECT id FROM products)'
    )->cnt;
    expect($danglingEnrolmentProducts)->toBe(0);
});

it('creates migration log entries for each phase', function () {
    $this->artisan('migrate:legacy', [
        '--tenant-id' => 1,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $logs = DB::table('migration_logs')->get();
    expect($logs->count())->toBeGreaterThan(0);

    // Should have logs from phases 1, 2, and 3
    $phases = $logs->pluck('phase')->unique()->toArray();
    expect($phases)->toContain('phase_1');
    expect($phases)->toContain('phase_2a');
    expect($phases)->toContain('phase_3a_invoices');

    // All logs should be completed
    $incomplete = $logs->whereNull('completed_at')->count();
    expect($incomplete)->toBe(0);
});

// ──────────────────────────────────────────────
// Custom Tenant ID
// ──────────────────────────────────────────────

it('supports custom tenant-id option', function () {
    // Create a second tenant
    DB::table('tenants')->insert([
        'id' => 2,
        'user_id' => 1,
        'name' => 'Second Tenant',
        'slug' => 'second-tenant',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy', [
        '--tenant-id' => 2,
        '--skip-media' => true,
        '--skip-validation' => true,
        '--to-phase' => 1,
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Centres should be under tenant 2
    expect(DB::table('centres')->where('tenant_id', 2)->count())->toBe(2);
    expect(DB::table('centres')->where('tenant_id', 1)->count())->toBe(0);
});
