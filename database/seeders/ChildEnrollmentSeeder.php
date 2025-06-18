<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Centre;
use App\Models\ChildEnrollment;
use App\Enums\ChildEnrollmentStatus;
use App\Enums\ChildEnrollmentBilledEvery;
use App\Enums\ChildEnrollmentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChildEnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing tenants, centres, children and products
        $tenants = Tenant::take(2)->get();
        $centres = Centre::take(3)->get();
        $children = Child::take(5)->get();
        $products = Product::take(3)->get();

        if ($tenants->isEmpty() || $centres->isEmpty() || $children->isEmpty() || $products->isEmpty()) {
            $this->command->info('No tenants, centres, children or products found. Creating sample data...');
            return;
        }

        // Create sample enrollments
        foreach ($tenants as $tenant) {
            foreach ($centres as $centre) {
                foreach ($children->random(3) as $child) {
                    foreach ($products->random(2) as $product) {
                        ChildEnrollment::create([
                            'tenant_id' => $tenant->id,
                            'centre_id' => $centre->id,
                            'child_id' => $child->id,
                            'product_id' => $product->id,
                            'status' => fake()->randomElement(ChildEnrollmentStatus::cases()),
                            'billed_every' => fake()->randomElement(ChildEnrollmentBilledEvery::cases()),
                            'date_start' => fake()->dateTimeBetween('-6 months', 'now'),
                            'date_end' => fake()->optional(0.7)->dateTimeBetween('now', '+1 year'),
                            'type' => fake()->randomElement(ChildEnrollmentType::cases()),
                        ]);
                    }
                }
            }
        }

        $this->command->info('Child enrollments created successfully!');
    }
}
