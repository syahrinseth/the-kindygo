<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(1, 1);
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
    $this->seedLegacyProducts(1, [1]);
    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1]);
    $this->seedLegacyUsers(1, 1);
    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1]);
    $this->seedLegacyChildren(1, 1, 1, 1);
    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1]);
});

it('migrates legacy quotations as expired history with their items', function () {
    $this->seedLegacyQuotations(1, 1, 1);
    $this->seedLegacyQuotationTransactions(1, 1, 1, 1, 1, 1);

    $this->artisan('migrate:legacy-quotations', ['--tenant-id' => 1])->assertSuccessful();

    $quotation = DB::table('quotations')->find(1);
    $item = DB::table('quotation_items')->find(1);

    expect($quotation->number)->toBe('QUO/2025/1')
        ->and($quotation->status)->toBe('expired')
        ->and($quotation->converted_invoice_id)->toBeNull()
        ->and($quotation->total_items)->toBe(1)
        ->and($quotation->total)->toBe(15000)
        ->and($item->quotation_id)->toBe(1)
        ->and($item->child_enrolment_id)->not->toBeNull()
        ->and($item->balance_amount)->toBe(15000)
        ->and($item->paid)->toBe(0);
});

it('generates a fallback number and skips target ID conflicts without overwriting them', function () {
    $this->seedLegacyQuotations(1, 1, 1);
    DB::connection('legacy')->table('1_quotations')->where('id', 1)->update(['quotation_no' => null]);

    $this->artisan('migrate:legacy-quotations', ['--tenant-id' => 1])->assertSuccessful();
    expect(DB::table('quotations')->find(1)->number)->toBe('LEGACY-QUO-1');

    DB::connection('legacy')->table('1_quotations')->insert([
        'id' => 2, 'quotation_no' => 'QUO/2025/2', 'parent_id' => 1, 'preschool_id' => 1,
        'date' => '2025-01-02 09:00:00', 'price' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('quotations')->insert([
        'id' => 2, 'number' => 'CURRENT-QUO-2', 'tenant_id' => 1, 'centre_id' => 1, 'user_id' => 1,
        'date' => now(), 'valid_until' => now(), 'status' => 'draft', 'total_items' => 0, 'total_amount' => 0, 'total' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-quotations', ['--tenant-id' => 1])->assertSuccessful();

    expect(DB::table('quotations')->find(2)->number)->toBe('CURRENT-QUO-2')
        ->and(DB::table('migration_orphans')->where('source_table', '1_quotations')->where('source_id', 2)->exists())->toBeTrue();
});

it('does not persist records during a dry run', function () {
    $this->seedLegacyQuotations(1, 1, 1);
    $this->seedLegacyQuotationTransactions(1, 1, 1, 1, 1, 1);

    $this->artisan('migrate:legacy-quotations', ['--tenant-id' => 1, '--dry-run' => true])->assertSuccessful();

    expect(DB::table('quotations')->count())->toBe(0)
        ->and(DB::table('quotation_items')->count())->toBe(0);
});
