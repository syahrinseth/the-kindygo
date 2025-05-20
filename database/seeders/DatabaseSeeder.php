<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        // Call the RoleSeeder
        $this->call([
            RoleSeeder::class,
        ]);

        // Create Super Admin role if it doesn't exist
        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        // Create Test User with password and assign role
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password@123'),
        ]);
        
        // Assign Super Admin role to the user
        $user->assignRole($role);

        // Create a default tenant
        $tenant = Tenant::create([
            'user_id' => $user->id,
            'name' => 'Default Tenant',
            'slug' => Str::slug('Default Tenant'),
            'personal_tenant' => false,
            'email' => 'tenant@example.com',
            'phone' => '123-456-7890',
            'address_1' => '123 Main St',
            'city' => 'Example City',
            'postal_code' => '12345',
            'state' => 'Example State',
        ]);

        // Assign the user to the tenant through the pivot table
        $tenant->users()->attach($user->id);
    }
}
