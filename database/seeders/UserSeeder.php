<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User with Tenant
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password@123'),
        ]);
        $admin->assignRole('Admin');

        $adminTenant = Tenant::create([
            'user_id' => $admin->id,
            'name' => 'Admin Tenant',
            'slug' => Str::slug('Admin Tenant'),
            'personal_tenant' => false,
            'email' => 'admin-tenant@example.com',
            'phone' => '123-456-7890',
            'address_1' => '123 Admin St',
            'city' => 'Admin City',
            'postal_code' => '12345',
            'state' => 'Admin State',
        ]);
        $adminTenant->users()->attach($admin->id);
        $admin->update(['current_tenant_id' => $adminTenant->id]);

        // Create Super Admin User
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password@123'),
        ]);
        $superAdmin->assignRole('Super Admin');
        $adminTenant->users()->attach($superAdmin->id);
        $superAdmin->update(['current_tenant_id' => $adminTenant->id]);

        // Create Teacher User
        $teacher = User::factory()->create([
            'name' => 'Teacher User',
            'email' => 'teacher@example.com',
            'password' => Hash::make('password@123'),
        ]);
        $teacher->assignRole('Teacher');
        $adminTenant->users()->attach($teacher->id);
        $teacher->update(['current_tenant_id' => $adminTenant->id]);

        // Create Parent User
        $parent = User::factory()->create([
            'name' => 'Parent User',
            'email' => 'parent@example.com',
            'password' => Hash::make('password@123'),
        ]);
        $parent->assignRole('Parent');
        $adminTenant->users()->attach($parent->id);
        $parent->update(['current_tenant_id' => $adminTenant->id]);

        // Create Test User with Tenant
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password@123'),
        ]);
        $testUser->assignRole('Admin');

        $testTenant = Tenant::create([
            'user_id' => $testUser->id,
            'name' => 'Test Tenant',
            'slug' => Str::slug('Test Tenant'),
            'personal_tenant' => false,
            'email' => 'test-tenant@example.com',
            'phone' => '987-654-3210',
            'address_1' => '456 Test Ave',
            'city' => 'Test City',
            'postal_code' => '54321',
            'state' => 'Test State',
        ]);
        $testTenant->users()->attach($testUser->id);
        $testUser->update(['current_tenant_id' => $testTenant->id]);
    }
}
