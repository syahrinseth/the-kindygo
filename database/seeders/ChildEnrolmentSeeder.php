<?php

namespace Database\Seeders;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ChildEnrolmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing tenants
        $tenants = Tenant::take(2)->get();

        if ($tenants->isEmpty()) {
            $this->command->info('No tenants found. Please run UserSeeder first.');

            return;
        }

        // Create sample enrolments for each tenant
        foreach ($tenants as $tenant) {
            // Get tenant-scoped centres, children and products
            $centres = $tenant->centres()->take(3)->get();
            $children = Child::whereHas('tenants', function ($query) use ($tenant) {
                $query->where('tenants.id', $tenant->id);
            })->take(5)->get();
            $products = Product::where('tenant_id', $tenant->id)->take(3)->get();

            if ($centres->isEmpty() || $children->isEmpty() || $products->isEmpty()) {
                $this->command->info("Skipping tenant {$tenant->name}: missing centres, children or products.");

                continue;
            }

            // Create enrolments with proper tenant isolation
            foreach ($centres as $centre) {
                foreach ($children->random(min(3, $children->count())) as $child) {
                    foreach ($products->random(min(2, $products->count())) as $product) {
                        ChildEnrolment::create([
                            'tenant_id' => $tenant->id,
                            'centre_id' => $centre->id,
                            'child_id' => $child->id,
                            'product_id' => $product->id,
                            'status' => fake()->randomElement(ChildEnrolmentStatus::cases()),
                            'billed_every' => fake()->randomElement(ChildEnrolmentBilledEvery::cases()),
                            'date_start' => fake()->dateTimeBetween('-6 months', 'now'),
                            'date_end' => fake()->optional(0.7)->dateTimeBetween('now', '+1 year'),
                            'type' => fake()->randomElement(ChildEnrolmentType::cases()),
                        ]);
                    }
                }
            }

            $this->command->info("Child enrolments created successfully for tenant: {$tenant->name}");
        }
    }
}
