<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Centre;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CentreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->info('No tenants found. Please run UserSeeder first.');

            return;
        }

        foreach ($tenants as $tenant) {
            // Get campuses for this tenant
            $campuses = $tenant->campuses;

            // Create centres with campus association (if campuses exist)
            if ($campuses->isNotEmpty()) {
                foreach ($campuses as $campus) {
                    // Create 2 centres per campus
                    Centre::factory()
                        ->count(2)
                        ->forCampus($campus)
                        ->create();

                    $this->command->info("Created 2 centres for campus: {$campus->name} (Tenant: {$tenant->name})");
                }
            }

            // Create 2 standalone centres (without campus) per tenant
            Centre::factory()
                ->count(2)
                ->create([
                    'tenant_id' => $tenant->id,
                    'campus_id' => null,
                ]);

            $this->command->info("Created 2 standalone centres for tenant: {$tenant->name}");
        }
    }
}
