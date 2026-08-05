<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleNames = [
            'Super Admin' => 'super-admin',
            'Admin' => 'admin',
            'Accountant' => 'accountant',
            'Principal' => 'principal',
            'Teacher' => 'teacher',
            'Parent' => 'parent',
            'Staff' => 'staff',
            'Auditor' => 'auditor',
            'Owner' => 'owner',
        ];

        foreach ($roleNames as $legacyName => $canonicalName) {
            $legacyRole = DB::table('roles')
                ->where('name', $legacyName)
                ->where('guard_name', 'web')
                ->first();

            if ($legacyRole === null) {
                continue;
            }

            $canonicalRole = DB::table('roles')
                ->where('name', $canonicalName)
                ->where('guard_name', 'web')
                ->first();

            if ($canonicalRole === null) {
                DB::table('roles')
                    ->where('id', $legacyRole->id)
                    ->update(['name' => $canonicalName]);

                continue;
            }

            foreach (DB::table('model_has_roles')->where('role_id', $legacyRole->id)->get() as $assignment) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $canonicalRole->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            }

            foreach (DB::table('role_has_permissions')->where('role_id', $legacyRole->id)->get() as $permission) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permission->permission_id,
                    'role_id' => $canonicalRole->id,
                ]);
            }

            DB::table('model_has_roles')->where('role_id', $legacyRole->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $legacyRole->id)->delete();
            DB::table('roles')->where('id', $legacyRole->id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('This migration merges duplicate role assignments and cannot be reversed safely.');
    }
};
