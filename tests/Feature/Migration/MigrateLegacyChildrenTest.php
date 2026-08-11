<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();

    // Children migration needs centres and products to exist
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(2, 1);
    $this->seedLegacyRoles();

    // Migrate prerequisites
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);

    // Seed and migrate products (required for child enrolments FK)
    $this->seedLegacyProducts(3, [1]);
    $this->artisan('migrate:legacy-products', ['--tenant-id' => 1]);

    // Seed and migrate users (required for child_user FK)
    $this->seedLegacyUsers(3, 1);
    $this->seedLegacyModelHasRoles([
        ['role_id' => 7, 'model_id' => 1],
        ['role_id' => 7, 'model_id' => 2],
        ['role_id' => 7, 'model_id' => 3],
    ]);
    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1]);
});

// ──────────────────────────────────────────────
// Core Child Migration
// ──────────────────────────────────────────────

it('supports process-level child ID ranges', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', [
        '--tenant-id' => 1,
        '--end-id' => 2,
    ])->assertSuccessful();

    expect(DB::table('children')->whereIn('id', [1, 2])->count())->toBe(2)
        ->and(DB::table('children')->where('id', 3)->exists())->toBeFalse();

    $this->artisan('migrate:legacy-children', [
        '--tenant-id' => 1,
        '--start-id' => 2,
    ])->assertSuccessful();

    expect(DB::table('children')->where('id', 3)->exists())->toBeTrue();
});

it('migrates legacy children to children table', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('children')->count())->toBe(3);

    $child = DB::table('children')->where('id', 1)->first();
    expect($child->first_name)->toBe('Child Firstname1');
    expect($child->last_name)->toBe('Lastname1');
});

it('preserves legacy child IDs', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    for ($i = 1; $i <= 3; $i++) {
        expect(DB::table('children')->where('id', $i)->exists())->toBeTrue();
    }
});

it('splits fullname into first_name and last_name', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Muhammad Ali Bin Ahmad',
        'dob' => '2020-01-15',
        'gender' => 1,
        'race' => 1,
        'religion' => 1,
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 10)->first();
    expect($child->first_name)->toBe('Muhammad Ali Bin');
    expect($child->last_name)->toBe('Ahmad');
});

it('migrates including soft-deleted children', function () {
    $this->seedLegacyChildren(2, parentId: 1, preschoolId: 1, productId: 1);

    // Add a soft-deleted child
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 99,
        'fullname' => 'Deleted Child',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'status' => 3,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Should include the soft-deleted child
    expect(DB::table('children')->count())->toBe(3);
    $deletedChild = DB::table('children')->where('id', 99)->first();
    expect($deletedChild)->not->toBeNull();
    expect($deletedChild->deleted_at)->not->toBeNull();
});

// ──────────────────────────────────────────────
// Child Profile Data
// ──────────────────────────────────────────────

it('maps gender correctly', function () {
    $this->seedLegacyChildren(2, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Seeded: odd IDs = gender 2 (male), even IDs = gender 1 (female) based on ($i % 2) + 1
    // Legacy mapping: 1 = female, 2 = male
    $child1 = DB::table('children')->where('id', 1)->first();
    $child2 = DB::table('children')->where('id', 2)->first();

    expect($child1->gender)->toBe('male');   // (1 % 2) + 1 = 2 → male
    expect($child2->gender)->toBe('female'); // (2 % 2) + 1 = 1 → female
});

it('maps race and religion correctly', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 1)->first();
    expect($child->race)->toBe('Malay');   // race=1 → Malay
    expect($child->religion)->toBe('Islam'); // religion=1 → Islam
});

it('migrates mykid_no, cert_number, and place_of_birth', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 1)->first();
    expect($child->mykid_no)->toBe('MYKID-1');
    expect($child->cert_number)->toBe('CERT-1');
    expect($child->place_of_birth)->toBe('Kuala Lumpur');
});

it('migrates family clinic data', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 1)->first();
    expect($child->family_clinic)->toBe('Clinic 1');
    expect($child->family_clinic_phone)->toBe('03123451');
});

it('parses languages JSON field', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 1)->first();
    $languages = json_decode($child->languages, true);
    expect($languages)->toBe(['Malay', 'English']);
});

it('parses allergies plain string into JSON array', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 1)->first();
    $allergies = json_decode($child->allergies, true);
    expect($allergies)->toBe(['Peanuts']);
});

it('handles allergies already in JSON format', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'JSON Allergy Child',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'status' => 1,
        'allergies' => json_encode(['Peanuts', 'Shellfish']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 10)->first();
    $allergies = json_decode($child->allergies, true);
    expect($allergies)->toBe(['Peanuts', 'Shellfish']);
});

it('maps position_of_child from post_of_child', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $child = DB::table('children')->where('id', 1)->first();
    expect($child->position_of_child)->toBe(1);
});

// ──────────────────────────────────────────────
// Child-User Pivot
// ──────────────────────────────────────────────

it('creates child_user pivot linking child to parent', function () {
    $this->seedLegacyChildren(2, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $pivots = DB::table('child_user')->where('user_id', 1)->get();
    expect($pivots)->toHaveCount(2);

    $pivot = $pivots->first();
    expect($pivot->relationship_type)->toBe('parent');
});

it('skips child_user pivot when parent_id is invalid', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Orphan Child Name',
        'parent_id' => 999, // Non-existent user
        'preschool_id' => 1,
        'product' => 1,
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Child should be created but no child_user pivot
    expect(DB::table('children')->where('id', 10)->exists())->toBeTrue();
    expect(DB::table('child_user')->where('child_id', 10)->exists())->toBeFalse();
    expect(DB::table('migration_orphans')->where('source_table', '1_child')->where('source_id', 10)->exists())->toBeTrue();
});

// ──────────────────────────────────────────────
// Tenant-Child Pivot
// ──────────────────────────────────────────────

it('creates tenant_child pivot with correct status mapping', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Statuses seeded: 1=new, 2=return, 3=alumni
    $pivot1 = DB::table('tenant_child')->where('child_id', 1)->first();
    $pivot2 = DB::table('tenant_child')->where('child_id', 2)->first();
    $pivot3 = DB::table('tenant_child')->where('child_id', 3)->first();

    expect($pivot1->status)->toBe('new');     // status 1 → NEW
    expect($pivot2->status)->toBe('return');   // status 2 → RETURN
    expect($pivot3->status)->toBe('alumni');   // status 3 → ALUMNI
    expect($pivot1->tenant_id)->toBe(1);
});

// ──────────────────────────────────────────────
// Centre-Child Pivot
// ──────────────────────────────────────────────

it('creates centre_child pivot from preschool_id', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $pivot = DB::table('centre_child')->where('child_id', 1)->first();
    expect($pivot)->not->toBeNull();
    expect($pivot->centre_id)->toBe(1);
});

it('logs orphan when preschool_id is invalid for centre_child', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Invalid Centre Child',
        'parent_id' => 1,
        'preschool_id' => 999,
        'product' => 1,
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('centre_child')->where('child_id', 10)->exists())->toBeFalse();
    expect(DB::table('migration_orphans')
        ->where('source_table', '1_child')
        ->where('source_id', 10)
        ->exists()
    )->toBeTrue();
});

// ──────────────────────────────────────────────
// Child Enrolments
// ──────────────────────────────────────────────

it('creates child_enrolments from product field', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 1)->first();
    expect($enrolment)->not->toBeNull();
    expect($enrolment->product_id)->toBe(1);
    expect($enrolment->centre_id)->toBe(1);
    expect($enrolment->tenant_id)->toBe(1);
    expect($enrolment->billed_every)->toBe('monthly');
});

it('maps child enrolment status correctly', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Statuses: 1=new→active, 2=return→active, 3=alumni→completed
    $enrolment1 = DB::table('child_enrolments')->where('child_id', 1)->first();
    $enrolment2 = DB::table('child_enrolments')->where('child_id', 2)->first();
    $enrolment3 = DB::table('child_enrolments')->where('child_id', 3)->first();

    expect($enrolment1->status)->toBe('active');
    expect($enrolment2->status)->toBe('active');
    expect($enrolment3->status)->toBe('completed');
});

it('maps enrolment type from legacy type field', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Trial Child Name',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'status' => 1,
        'type' => 'trial',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 10)->first();
    expect($enrolment->type)->toBe('trial');
});

it('defaults enrolment type to full_time when type is null', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 1)->first();
    expect($enrolment->type)->toBe('full_time');
});

it('uses is_registered date as date_start when available', function () {
    $this->seedLegacyChildren(1, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 1)->first();
    // Seeded is_registered = '2024-01-15'
    expect($enrolment->date_start)->toContain('2024-01-15');
});

it('falls back to created_at when is_registered is too short', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Short Registered Child',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'status' => 1,
        'is_registered' => 'yes',
        'created_at' => '2024-06-01 10:00:00',
        'updated_at' => '2024-06-01 10:00:00',
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 10)->first();
    expect($enrolment->date_start)->toContain('2024-06-01');
});

it('skips enrolment when product is invalid', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Invalid Product Child',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 999,
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('children')->where('id', 10)->exists())->toBeTrue();
    expect(DB::table('child_enrolments')->where('child_id', 10)->exists())->toBeFalse();
    expect(DB::table('migration_orphans')
        ->where('source_table', '1_child')
        ->where('source_id', 10)
        ->exists()
    )->toBeTrue();
});

it('skips enrolment when product field is zero', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'No Product Child Name',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 0,
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('children')->where('id', 10)->exists())->toBeTrue();
    expect(DB::table('child_enrolments')->where('child_id', 10)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// Additional Products
// ──────────────────────────────────────────────

it('parses other_products into additional_products JSON', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Multi Product Child',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'other_products' => json_encode([2, 3]),
        'status' => 1,
        'is_registered' => '2024-03-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 10)->first();
    $additionalProducts = json_decode($enrolment->additional_products, true);

    expect($additionalProducts)->toHaveCount(2);
    expect($additionalProducts[0]['product_id'])->toBe(2);
    expect($additionalProducts[1]['product_id'])->toBe(3);
    expect($additionalProducts[0]['billed_every'])->toBe('monthly');
});

it('ignores invalid product IDs in other_products', function () {
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 10,
        'fullname' => 'Mixed Products Child',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'other_products' => json_encode([2, 999]),
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $enrolment = DB::table('child_enrolments')->where('child_id', 10)->first();
    $additionalProducts = json_decode($enrolment->additional_products, true);

    // Only product 2 should be included; 999 should be skipped and logged as orphan
    expect($additionalProducts)->toHaveCount(1);
    expect($additionalProducts[0]['product_id'])->toBe(2);
});

// ──────────────────────────────────────────────
// Idempotency & Modes
// ──────────────────────────────────────────────

it('is idempotent - re-running does not create duplicates', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $childCount1 = DB::table('children')->count();
    $enrolmentCount1 = DB::table('child_enrolments')->count();
    $pivotCount1 = DB::table('child_user')->count();
    $tenantChildCount1 = DB::table('tenant_child')->count();

    // Run again
    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('children')->count())->toBe($childCount1);
    expect(DB::table('child_enrolments')->count())->toBe($enrolmentCount1);
    expect(DB::table('child_user')->count())->toBe($pivotCount1);
    expect(DB::table('tenant_child')->count())->toBe($tenantChildCount1);
});

it('does not make changes in dry-run mode', function () {
    $this->seedLegacyChildren(3, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--dry-run' => true])
        ->assertSuccessful();

    expect(DB::table('children')->count())->toBe(0);
    expect(DB::table('child_enrolments')->count())->toBe(0);
    expect(DB::table('child_user')->count())->toBe(0);
    expect(DB::table('tenant_child')->count())->toBe(0);
    expect(DB::table('centre_child')->count())->toBe(0);
});

it('skip-existing mode skips already migrated children', function () {
    $this->seedLegacyChildren(2, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('children')->count())->toBe(2);

    // Add a third child and run with skip-existing
    DB::connection('legacy')->table('1_child')->insert([
        'id' => 3,
        'fullname' => 'New Child Three',
        'parent_id' => 1,
        'preschool_id' => 1,
        'product' => 1,
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1, '--skip-existing' => true])
        ->assertSuccessful();

    expect(DB::table('children')->count())->toBe(3);
});

// ──────────────────────────────────────────────
// Migration Logs
// ──────────────────────────────────────────────

it('creates migration log entries for children', function () {
    $this->seedLegacyChildren(2, parentId: 1, preschoolId: 1, productId: 1);

    $this->artisan('migrate:legacy-children', ['--tenant-id' => 1])
        ->assertSuccessful();

    $log = DB::table('migration_logs')
        ->where('phase', 'phase_2b')
        ->where('source_table', '1_child')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->total_migrated)->toBe(2);
    expect($log->completed_at)->not->toBeNull();
});
