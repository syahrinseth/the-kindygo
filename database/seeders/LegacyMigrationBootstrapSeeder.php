<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyMigrationBootstrapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Legacy Migration Bootstrap Owner',
            'email' => 'legacy-migration-bootstrap@kindygo.invalid',
            'email_verified_at' => now(),
            'password' => Hash::make(bin2hex(random_bytes(32))),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenants')->insert([
            'id' => 1,
            'user_id' => 1,
            'name' => 'Admin Tenant',
            'slug' => 'admin-tenant',
            'personal_tenant' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
