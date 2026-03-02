<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();

    // Products migration needs centres to exist
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(2, 1);

    // Migrate centres first (prerequisite)
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
});

// ──────────────────────────────────────────────
// Core Product Migration
// ──────────────────────────────────────────────

it('migrates legacy products to products table', function () {
    $this->seedLegacyProducts(3, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('products')->count())->toBe(3);

    $product = DB::table('products')->where('id', 1)->first();
    expect($product->name)->toBe('Test Product 1');
    expect($product->tenant_id)->toBe(1);
    expect($product->code)->not->toBeEmpty();
    expect($product->status)->toBe('active');
});

it('preserves legacy product IDs', function () {
    $this->seedLegacyProducts(3, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    for ($i = 1; $i <= 3; $i++) {
        expect(DB::table('products')->where('id', $i)->exists())->toBeTrue();
    }
});

it('maps product types correctly', function () {
    $this->seedLegacyProducts(3, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Types seeded: 1=programme, 2=event, 6=service
    $product1 = DB::table('products')->where('id', 1)->first();
    $product2 = DB::table('products')->where('id', 2)->first();
    $product3 = DB::table('products')->where('id', 3)->first();

    expect($product1->type)->toBe('programme');
    expect($product2->type)->toBe('event');
    expect($product3->type)->toBe('service');
});

it('stores product description from legacy remarks', function () {
    $this->seedLegacyProducts(1, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $product = DB::table('products')->where('id', 1)->first();
    expect($product->description)->toBe('Test product 1 remarks');
});

it('skips soft-deleted products', function () {
    $this->seedLegacyProducts(2, [1]);

    DB::connection('legacy')->table('1_product')->insert([
        'id' => 99,
        'name' => 'Deleted Product',
        'product_type' => 1,
        'status' => 'active',
        'price' => 100,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('products')->where('id', 99)->exists())->toBeFalse();
});

it('maps inactive status for non-active products', function () {
    DB::connection('legacy')->table('1_product')->insert([
        'id' => 1,
        'name' => 'Inactive Product',
        'product_type' => 1,
        'status' => 'disabled',
        'price' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $product = DB::table('products')->where('id', 1)->first();
    expect($product->status)->toBe('inactive');
});

it('is idempotent - re-running does not create duplicates', function () {
    $this->seedLegacyProducts(3, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $count1 = DB::table('products')->count();
    $priceCount1 = DB::table('product_prices')->count();

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('products')->count())->toBe($count1);
    expect(DB::table('product_prices')->count())->toBe($priceCount1);
});

it('does not make changes in dry-run mode', function () {
    $this->seedLegacyProducts(3, [1]);

    $this->artisan('migrate:legacy-products', ['--dry-run' => true])
        ->assertSuccessful();

    expect(DB::table('products')->count())->toBe(0);
    expect(DB::table('product_prices')->count())->toBe(0);
});

it('generates unique codes for products with same name', function () {
    DB::connection('legacy')->table('1_product')->insert([
        'id' => 1, 'name' => 'Same Product', 'product_type' => 1,
        'status' => 'active', 'price' => 100, 'year' => 2025,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('legacy')->table('1_product')->insert([
        'id' => 2, 'name' => 'Same Product', 'product_type' => 1,
        'status' => 'active', 'price' => 200, 'year' => 2025,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $codes = DB::table('products')->pluck('code')->toArray();
    expect(count(array_unique($codes)))->toBe(count($codes));
});

// ──────────────────────────────────────────────
// Product Prices
// ──────────────────────────────────────────────

it('migrates current price and price history to product_prices', function () {
    $this->seedLegacyProducts(1, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Seeded: current price=150 at year=2025, history=[{year:2024, price:130}]
    $prices = DB::table('product_prices')
        ->where('product_id', 1)
        ->orderBy('start_date')
        ->get();

    expect($prices)->toHaveCount(2);

    // Historical price: year 2024, price 130 RM → 13000 cents
    expect($prices[0]->start_date)->toBe('2024-01-01');
    expect($prices[0]->price)->toBe(13000);
    expect($prices[0]->end_date)->toBe('2024-12-31');

    // Current price: year 2025, price 150 RM → 15000 cents
    expect($prices[1]->start_date)->toBe('2025-01-01');
    expect($prices[1]->price)->toBe(15000);
    expect($prices[1]->end_date)->toBeNull(); // Last entry has no end date
});

it('converts prices from RM to cents', function () {
    DB::connection('legacy')->table('1_product')->insert([
        'id' => 1, 'name' => 'Price Test', 'product_type' => 1,
        'status' => 'active', 'price' => 250, 'year' => 2025,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $price = DB::table('product_prices')->where('product_id', 1)->first();
    expect($price->price)->toBe(25000); // 250 RM × 100 = 25000 cents
});

// ──────────────────────────────────────────────
// Product-Centre Pivot
// ──────────────────────────────────────────────

it('creates product-centre relationships from preschool JSON', function () {
    $this->seedLegacyProducts(1, [1, 2]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $centreIds = DB::table('product_centre')
        ->where('product_id', 1)
        ->pluck('centre_id')
        ->sort()
        ->values()
        ->toArray();

    expect($centreIds)->toBe([1, 2]);
});

it('logs orphan when product references non-existent centre', function () {
    DB::connection('legacy')->table('1_product')->insert([
        'id' => 1, 'name' => 'Orphan Product', 'product_type' => 1,
        'status' => 'active', 'price' => 100, 'year' => 2025,
        'preschool' => json_encode([999]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('migration_orphans')
        ->where('source_table', '1_product')
        ->where('source_id', 1)
        ->exists()
    )->toBeTrue();
});

// ──────────────────────────────────────────────
// Migration Logs
// ──────────────────────────────────────────────

it('creates migration log entries for products', function () {
    $this->seedLegacyProducts(2, [1]);

    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1])
        ->assertSuccessful();

    $log = DB::table('migration_logs')
        ->where('phase', 'phase_2c')
        ->where('source_table', '1_product')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->total_migrated)->toBe(2);
    expect($log->completed_at)->not->toBeNull();
});
