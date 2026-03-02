<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();
});

// ──────────────────────────────────────────────
// Campus Migration Tests
// ──────────────────────────────────────────────

it('migrates legacy campuses to campuses table', function () {
    $this->seedLegacyCampuses(3);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('campuses')->count())->toBe(3);

    $campus = DB::table('campuses')->where('id', 1)->first();
    expect($campus->name)->toBe('Test Campus 1');
    expect($campus->tenant_id)->toBe(1);
    expect($campus->phone)->toBe('012345671');
});

it('skips soft-deleted campuses', function () {
    $this->seedLegacyCampuses(2);

    DB::connection('legacy')->table('1_campuses')->insert([
        'id' => 99,
        'name' => 'Deleted Campus',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('campuses')->count())->toBe(2);
    expect(DB::table('campuses')->where('id', 99)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// Centre Migration Tests
// ──────────────────────────────────────────────

it('migrates legacy preschools to centres table', function () {
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(3, 1);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('centres')->count())->toBe(3);

    $centre = DB::table('centres')->where('id', 1)->first();
    expect($centre->name)->toBe('Test Preschool 1');
    expect($centre->tenant_id)->toBe(1);
    expect($centre->campus_id)->toBe(1);
    expect($centre->code)->not->toBeEmpty();
    expect($centre->slug)->not->toBeEmpty();
});

it('maps centre statuses correctly', function () {
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(3, 1);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Status: active, close, licensee → active, inactive, active
    $centre1 = DB::table('centres')->where('id', 1)->first();
    $centre2 = DB::table('centres')->where('id', 2)->first();
    $centre3 = DB::table('centres')->where('id', 3)->first();

    expect($centre1->status)->toBe('active');
    expect($centre2->status)->toBe('inactive');
    expect($centre3->status)->toBe('active');
});

it('stores legacy status in meta_data when status changes', function () {
    $this->seedLegacyCampuses(1);

    DB::connection('legacy')->table('1_preschool')->insert([
        'id' => 10,
        'name' => 'Closed Centre',
        'short_name' => 'CC',
        'campus_id' => 1,
        'status' => 'close',
        'ssm_comp_name' => 'SSM Company',
        'ssm_no' => 'SSM-123',
        'capacity' => 75,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    $centre = DB::table('centres')->where('id', 10)->first();
    $meta = json_decode($centre->meta_data, true);

    expect($meta['legacy_status'])->toBe('close');
    expect($meta['legacy_ssm_comp_name'])->toBe('SSM Company');
    expect($meta['legacy_ssm_no'])->toBe('SSM-123');
    expect($meta['legacy_capacity'])->toBe(75);
});

it('generates unique slugs and codes for centres', function () {
    $this->seedLegacyCampuses(1);

    // Insert two preschools with the same name
    DB::connection('legacy')->table('1_preschool')->insert([
        'id' => 1, 'name' => 'Same Name', 'short_name' => 'SN',
        'campus_id' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::connection('legacy')->table('1_preschool')->insert([
        'id' => 2, 'name' => 'Same Name', 'short_name' => 'SN',
        'campus_id' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    $slugs = DB::table('centres')->pluck('slug')->toArray();
    $codes = DB::table('centres')->pluck('code')->toArray();

    // All slugs and codes should be unique
    expect(count(array_unique($slugs)))->toBe(count($slugs));
    expect(count(array_unique($codes)))->toBe(count($codes));
});

it('sets campus_id to null when campus does not exist', function () {
    // Don't seed any campuses
    DB::connection('legacy')->table('1_preschool')->insert([
        'id' => 1, 'name' => 'Orphan Centre', 'short_name' => 'OC',
        'campus_id' => 999, 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    $centre = DB::table('centres')->where('id', 1)->first();
    expect($centre->campus_id)->toBeNull();

    // Should log orphan
    expect(DB::table('migration_orphans')->count())->toBeGreaterThanOrEqual(1);
});

it('is idempotent - re-running does not create duplicates', function () {
    $this->seedLegacyCampuses(2);
    $this->seedLegacyPreschools(2, 1);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    $campusCount1 = DB::table('campuses')->count();
    $centreCount1 = DB::table('centres')->count();

    // Run again
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('campuses')->count())->toBe($campusCount1);
    expect(DB::table('centres')->count())->toBe($centreCount1);
});

it('does not make changes in dry-run mode', function () {
    $this->seedLegacyCampuses(2);
    $this->seedLegacyPreschools(2, 1);

    $this->artisan('migrate:legacy-centres', ['--dry-run' => true])
        ->assertSuccessful();

    expect(DB::table('campuses')->count())->toBe(0);
    expect(DB::table('centres')->count())->toBe(0);
});

it('creates migration log entries', function () {
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(2, 1);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    $logs = DB::table('migration_logs')->where('phase', 'phase_1')->get();
    expect($logs)->toHaveCount(2); // one for campuses, one for centres

    $campusLog = $logs->firstWhere('source_table', '1_campuses');
    expect($campusLog->total_migrated)->toBe(1);
    expect($campusLog->completed_at)->not->toBeNull();

    $centreLog = $logs->firstWhere('source_table', '1_preschool');
    expect($centreLog->total_migrated)->toBe(2);
    expect($centreLog->completed_at)->not->toBeNull();
});

it('maps legacy state IDs to Malaysian state values', function () {
    $this->seedLegacyCampuses(1);

    DB::connection('legacy')->table('1_preschool')->insert([
        'id' => 1, 'name' => 'KL Centre', 'short_name' => 'KL',
        'campus_id' => 1, 'status' => 'active', 'state' => '12',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1])
        ->assertSuccessful();

    $centre = DB::table('centres')->where('id', 1)->first();
    expect($centre->state)->toBe('14'); // MalaysianState::WP_KUALA_LUMPUR->value
});

// ──────────────────────────────────────────────
// Roles Migration Tests
// ──────────────────────────────────────────────

it('creates required roles during migration', function () {
    $this->seedLegacyRoles();

    $this->artisan('migrate:legacy-roles')
        ->assertSuccessful();

    // Should have all 9 unique target roles
    $expectedRoles = ['Super Admin', 'Admin', 'Accountant', 'Principal', 'Teacher', 'Parent', 'Staff', 'Auditor', 'Owner'];

    foreach ($expectedRoles as $role) {
        expect(DB::table('roles')->where('name', $role)->where('guard_name', 'web')->exists())->toBeTrue(
            "Role '{$role}' should exist"
        );
    }
});

it('does not duplicate existing roles', function () {
    $this->seedLegacyRoles();

    // Pre-create some roles
    DB::table('roles')->insert([
        'name' => 'Super Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('roles')->insert([
        'name' => 'Parent', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-roles')
        ->assertSuccessful();

    $superAdminCount = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'web')->count();
    expect($superAdminCount)->toBe(1);
});

it('maps legacy role IDs correctly', function () {
    expect(\App\Console\Commands\MigrateLegacyRoles::getTargetRoleName(1))->toBe('Super Admin');
    expect(\App\Console\Commands\MigrateLegacyRoles::getTargetRoleName(7))->toBe('Parent');
    expect(\App\Console\Commands\MigrateLegacyRoles::getTargetRoleName(10))->toBeNull(); // Application = skip
    expect(\App\Console\Commands\MigrateLegacyRoles::getTargetRoleName(12))->toBe('Owner');
    expect(\App\Console\Commands\MigrateLegacyRoles::getTargetRoleName(99))->toBeNull(); // Unknown
});
