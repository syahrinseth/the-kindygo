<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call seeders in order
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CampusSeeder::class,
            CentreSeeder::class,
            ProductSeeder::class,
            InvoiceSeeder::class,
            InvoiceItemSeeder::class,
            ChildEnrolmentSeeder::class,
        ]);
    }
}
