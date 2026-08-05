<?php

use Illuminate\Support\Facades\DB;
use Tests\Traits\LegacyMigrationTestHelper;

uses(LegacyMigrationTestHelper::class);

beforeEach(function () {
    $this->setUpLegacyDatabase();
    $this->createTestTenant();

    // Users migration needs centres to exist and roles to exist
    $this->seedLegacyCampuses(1);
    $this->seedLegacyPreschools(2, 1);
    $this->seedLegacyRoles();

    // Migrate centres and roles first (prerequisite)
    $this->artisan('migrate:legacy-centres', ['--tenant-id' => 1]);
    $this->artisan('migrate:legacy-roles');
});

// ──────────────────────────────────────────────
// Core User Migration
// ──────────────────────────────────────────────

it('migrates legacy users to users table', function () {
    $this->seedLegacyUsers(3, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    // 3 migrated users (id 1 overwrites tenant owner)
    expect(DB::table('users')->count())->toBe(3);

    $user = DB::table('users')->where('id', 1)->first();
    expect($user->name)->toBe('Test User 1');
    expect($user->email)->toBe('testuser1@example.com');
    expect($user->current_tenant_id)->toBe(1);
});

it('preserves legacy user IDs', function () {
    $this->seedLegacyUsers(3, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    for ($i = 1; $i <= 3; $i++) {
        expect(DB::table('users')->where('id', $i)->exists())->toBeTrue(
            "User with ID {$i} should exist"
        );
    }
});

it('preserves legacy hashed passwords without double-hashing', function () {
    $this->seedLegacyUsers(1, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $user = DB::table('users')->where('id', 1)->first();
    expect($user->password)->toBe('$2y$10$hashedpassword');
});

it('skips soft-deleted legacy users', function () {
    $this->seedLegacyUsers(2, 1);

    DB::connection('legacy')->table('1_users')->insert([
        'id' => 99,
        'name' => 'Deleted User',
        'email' => 'deleted@example.com',
        'password' => '$2y$10$hashedpassword',
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => now(),
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('users')->where('id', 99)->exists())->toBeFalse();
});

it('is idempotent - re-running does not create duplicates', function () {
    $this->seedLegacyUsers(2, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $count1 = DB::table('users')->count();
    $profileCount1 = DB::table('user_profiles')->count();

    // Run again
    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('users')->count())->toBe($count1);
    expect(DB::table('user_profiles')->count())->toBe($profileCount1);
});

it('does not make changes in dry-run mode', function () {
    $this->seedLegacyUsers(2, 1);

    $this->artisan('migrate:legacy-users', ['--dry-run' => true, '--tenant-id' => 1])
        ->assertSuccessful();

    // Only the tenant owner user exists
    expect(DB::table('users')->count())->toBe(1);
    expect(DB::table('user_profiles')->count())->toBe(0);
});

// ──────────────────────────────────────────────
// User Profile Migration
// ──────────────────────────────────────────────

it('migrates user profile data (ic_no, phone, occupation)', function () {
    $this->seedLegacyUsers(1, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $profile = DB::table('user_profiles')->where('user_id', 1)->first();
    expect($profile)->not->toBeNull();
    expect($profile->nric)->toBe('990101-01-0001');
    expect($profile->phone)->toBe('01123451');
    expect($profile->occupation)->toBe('Engineer');
});

it('skips user profile when no profile data exists', function () {
    // Insert user with no profile fields
    DB::connection('legacy')->table('1_users')->insert([
        'id' => 50,
        'name' => 'No Profile User',
        'email' => 'noprofile@example.com',
        'password' => '$2y$10$hashedpassword',
        'user_status' => 1,
        'preschool' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('user_profiles')->where('user_id', 50)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// User Address Migration
// ──────────────────────────────────────────────

it('migrates user address data', function () {
    $this->seedLegacyUsers(1, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $address = DB::table('user_addresses')->where('user_id', 1)->first();
    expect($address)->not->toBeNull();
    expect($address->address)->toBe('No. 1 User Street');
    expect($address->city)->toBe('Petaling Jaya');
    expect($address->postal_code)->toBe('47000');
    // State 10 => MalaysianState::SELANGOR->value = '10'
    expect($address->state_code)->toBe('10');
});

it('normalises legacy string state values during user migration', function () {
    $this->seedLegacyUsers(1, 1);

    DB::connection('legacy')->table('1_users')->where('id', 1)->update([
        'state' => 'SGR',
        'company_state' => 'KUL',
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('user_addresses')->where('user_id', 1)->value('state_code'))->toBe('10')
        ->and(DB::table('user_office_infos')->where('user_id', 1)->value('office_state_code'))->toBe('14');
});

it('does not persist unknown legacy state values', function () {
    $this->seedLegacyUsers(1, 1);

    DB::connection('legacy')->table('1_users')->where('id', 1)->update([
        'state' => 'Canada',
        'company_state' => 'N/a',
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('user_addresses')->where('user_id', 1)->value('state_code'))->toBeNull()
        ->and(DB::table('user_office_infos')->where('user_id', 1)->value('office_state_code'))->toBeNull();
});

// ──────────────────────────────────────────────
// User Office Info Migration
// ──────────────────────────────────────────────

it('migrates user office info', function () {
    $this->seedLegacyUsers(1, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $office = DB::table('user_office_infos')->where('user_id', 1)->first();
    expect($office)->not->toBeNull();
    expect($office->office_name)->toBe('Test Company 1');
    expect($office->office_phone)->toBe('03123451');
    expect($office->office_address)->toBe('Suite 1');
    expect($office->office_city)->toBe('Kuala Lumpur');
    expect($office->office_postal_code)->toBe('50000');
    // Legacy state ID 12 → MalaysianState::WP_KUALA_LUMPUR->value = '14'
    expect($office->office_state_code)->toBe('14');
});

// ──────────────────────────────────────────────
// Spouse / Family Member Migration
// ──────────────────────────────────────────────

it('migrates spouse data to family_members table', function () {
    $this->seedLegacyUsers(2, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    // Users 1 and 2 have spouse data; user 3 does not
    $member = DB::table('family_members')->where('user_id', 1)->first();
    expect($member)->not->toBeNull();
    expect($member->name)->toBe('Spouse 1');
    expect($member->relationship_type)->toBe('spouse');
    expect($member->nric)->toBe('880101-01-0001');
    expect($member->phone)->toBe('01987651');
});

it('does not create family member when no spouse data exists', function () {
    // Insert user without spouse
    DB::connection('legacy')->table('1_users')->insert([
        'id' => 50,
        'name' => 'No Spouse User',
        'email' => 'nospouse@example.com',
        'password' => '$2y$10$hashedpassword',
        'user_status' => 1,
        'preschool' => 1,
        'ic_no' => '123456',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('family_members')->where('user_id', 50)->exists())->toBeFalse();
});

// ──────────────────────────────────────────────
// Tenant-User Pivot
// ──────────────────────────────────────────────

it('attaches users to tenant via tenant_user pivot', function () {
    $this->seedLegacyUsers(3, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('tenant_user')->where('tenant_id', 1)->count())->toBe(3);

    $pivot = DB::table('tenant_user')
        ->where('tenant_id', 1)
        ->where('user_id', 1)
        ->first();
    expect($pivot)->not->toBeNull();
    expect($pivot->current_centre_id)->toBe(1); // preschool=1
});

// ──────────────────────────────────────────────
// Centre-User Pivot
// ──────────────────────────────────────────────

it('attaches users to centres via centre_user pivot', function () {
    $this->seedLegacyUsers(1, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('centre_user')->where('user_id', 1)->exists())->toBeTrue();
});

it('attaches user to multiple centres from other_preschools', function () {
    DB::connection('legacy')->table('1_users')->insert([
        'id' => 50,
        'name' => 'Multi Centre User',
        'email' => 'multi@example.com',
        'password' => '$2y$10$hashedpassword',
        'user_status' => 1,
        'preschool' => 1,
        'other_preschools' => json_encode([2]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $centreIds = DB::table('centre_user')
        ->where('user_id', 50)
        ->pluck('centre_id')
        ->sort()
        ->values()
        ->toArray();

    expect($centreIds)->toBe([1, 2]);
});

it('logs orphan when centre does not exist', function () {
    DB::connection('legacy')->table('1_users')->insert([
        'id' => 50,
        'name' => 'Orphan Centre User',
        'email' => 'orphan@example.com',
        'password' => '$2y$10$hashedpassword',
        'user_status' => 1,
        'preschool' => 999, // does not exist
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('migration_orphans')
        ->where('source_table', '1_users')
        ->where('source_id', 50)
        ->exists()
    )->toBeTrue();
});

// ──────────────────────────────────────────────
// Role Migration
// ──────────────────────────────────────────────

it('migrates user role assignments to model_has_roles', function () {
    $this->seedLegacyUsers(2, 1);
    $this->seedLegacyModelHasRoles([
        ['role_id' => 7, 'model_id' => 1],  // Parent
        ['role_id' => 2, 'model_id' => 2],  // Admin
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $parentRoleId = DB::table('roles')->where('name', 'parent')->value('id');
    $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

    expect(DB::table('model_has_roles')
        ->where('role_id', $parentRoleId)
        ->where('model_id', 1)
        ->where('model_type', 'App\\Models\\User')
        ->exists()
    )->toBeTrue();

    expect(DB::table('model_has_roles')
        ->where('role_id', $adminRoleId)
        ->where('model_id', 2)
        ->where('model_type', 'App\\Models\\User')
        ->exists()
    )->toBeTrue();
});

it('skips legacy role ID 10 (Application)', function () {
    $this->seedLegacyUsers(1, 1);
    $this->seedLegacyModelHasRoles([
        ['role_id' => 10, 'model_id' => 1],  // Application = skip
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    expect(DB::table('model_has_roles')
        ->where('model_id', 1)
        ->where('model_type', 'App\\Models\\User')
        ->count()
    )->toBe(0);
});

it('maps Account and Account Staff to Accountant role', function () {
    $this->seedLegacyUsers(2, 1);
    $this->seedLegacyModelHasRoles([
        ['role_id' => 3, 'model_id' => 1],  // Account → Accountant
        ['role_id' => 5, 'model_id' => 2],  // Account Staff → Accountant
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $accountantRoleId = DB::table('roles')->where('name', 'accountant')->value('id');

    expect(DB::table('model_has_roles')
        ->where('role_id', $accountantRoleId)
        ->where('model_type', 'App\\Models\\User')
        ->count()
    )->toBe(2);
});

// ──────────────────────────────────────────────
// Meta Data
// ──────────────────────────────────────────────

it('stores legacy user status in meta_data', function () {
    $this->seedLegacyUsers(1, 1);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $user = DB::table('users')->where('id', 1)->first();
    $meta = json_decode($user->meta_data, true);

    expect($meta['legacy_user_status'])->toBe(1);
    expect($meta['legacy_user_status_name'])->toBe('Normal');
    expect($meta['legacy_id'])->toBe(1);
});

// ──────────────────────────────────────────────
// Migration Log Entries
// ──────────────────────────────────────────────

it('creates migration log entries for users and roles', function () {
    $this->seedLegacyUsers(2, 1);
    $this->seedLegacyModelHasRoles([
        ['role_id' => 7, 'model_id' => 1],
    ]);

    $this->artisan('migrate:legacy-users', ['--tenant-id' => 1])
        ->assertSuccessful();

    $userLog = DB::table('migration_logs')
        ->where('phase', 'phase_2a')
        ->where('source_table', '1_users')
        ->first();

    expect($userLog)->not->toBeNull();
    expect($userLog->total_migrated)->toBe(2);
    expect($userLog->completed_at)->not->toBeNull();

    $roleLog = DB::table('migration_logs')
        ->where('phase', 'phase_2a')
        ->where('source_table', '1_model_has_roles')
        ->first();

    expect($roleLog)->not->toBeNull();
});
