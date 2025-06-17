<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Centre;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing tenants and centres
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->command->info('No tenants found. Please create tenants first.');
            return;
        }

        foreach ($tenants as $tenant) {
            $centres = $tenant->centres;
            
            // Create some global products (not tied to specific centre)
            Product::factory()->count(5)->create([
                'tenant_id' => $tenant->id,
                'centre_id' => null, // Global products
            ]);

            // Create centre-specific products
            foreach ($centres as $centre) {
                Product::factory()->count(3)->create([
                    'tenant_id' => $tenant->id,
                    'centre_id' => $centre->id,
                ]);
            }
        }

        $this->command->info('Products seeded successfully!');
    }
}
