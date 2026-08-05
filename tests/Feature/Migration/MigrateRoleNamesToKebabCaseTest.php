<?php

use Illuminate\Support\Facades\DB;

test('merges title-cased roles into canonical kebab-case roles without losing assignments or permissions', function () {
    $legacyRoleId = DB::table('roles')->insertGetId([
        'name' => 'Parent',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $canonicalRoleId = DB::table('roles')->insertGetId([
        'name' => 'parent',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'name' => 'view children',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('model_has_roles')->insert([
        ['role_id' => $legacyRoleId, 'model_type' => 'App\\Models\\User', 'model_id' => 1],
        ['role_id' => $canonicalRoleId, 'model_type' => 'App\\Models\\User', 'model_id' => 1],
    ]);
    DB::table('role_has_permissions')->insert([
        'permission_id' => $permissionId,
        'role_id' => $legacyRoleId,
    ]);

    $migration = require database_path('migrations/2026_08_03_091011_migrate_role_names_to_kebab_case.php');
    $migration->up();

    expect(DB::table('roles')->where('name', 'Parent')->exists())->toBeFalse();
    expect(DB::table('roles')->where('name', 'parent')->count())->toBe(1);
    expect(DB::table('model_has_roles')->where('role_id', $canonicalRoleId)->count())->toBe(1);
    expect(DB::table('role_has_permissions')
        ->where('role_id', $canonicalRoleId)
        ->where('permission_id', $permissionId)
        ->exists())->toBeTrue();
});
