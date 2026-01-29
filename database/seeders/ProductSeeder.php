<?php

namespace Database\Seeders;

use App\Models\Centre;
use App\Models\Product;
use App\Models\Tenant;
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
            $globalProducts = Product::factory()->count(5)->create([
                'tenant_id' => $tenant->id,
            ]);

            // If there are centres, attach global products to all centres
            if ($centres->isNotEmpty()) {
                foreach ($globalProducts as $product) {
                    $product->centres()->attach($centres->pluck('id'));
                }
            }

            // Create centre-specific products
            foreach ($centres as $centre) {
                $centreProducts = Product::factory()->count(3)->create([
                    'tenant_id' => $tenant->id,
                ]);

                // Attach each centre-specific product to its centre
                foreach ($centreProducts as $product) {
                    $product->centres()->attach($centre->id);
                }
            }
        }

        $this->command->info('Products seeded successfully!');
    }
}
