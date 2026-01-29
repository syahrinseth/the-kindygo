<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
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

        $campusData = [
            [
                'name' => 'Main Campus',
                'phone' => '03-1234-5678',
                'address_1' => '123 Jalan Utama',
                'address_2' => 'Taman Sejahtera',
                'postal_code' => '50000',
                'city' => 'Kuala Lumpur',
                'state' => 'Wilayah Persekutuan',
            ],
            [
                'name' => 'North Campus',
                'phone' => '04-9876-5432',
                'address_1' => '456 Jalan Utara',
                'address_2' => null,
                'postal_code' => '10000',
                'city' => 'George Town',
                'state' => 'Pulau Pinang',
            ],
        ];

        foreach ($tenants as $tenant) {
            // Skip if tenant already has campuses
            if ($tenant->campuses()->exists()) {
                $this->command->info("Tenant {$tenant->name} already has campuses. Skipping.");

                continue;
            }

            foreach ($campusData as $index => $data) {
                Campus::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => strtolower(str_replace(' ', '-', $data['name']))."@{$tenant->slug}.com",
                    'address_1' => $data['address_1'],
                    'address_2' => $data['address_2'],
                    'postal_code' => $data['postal_code'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                ]);
            }

            $this->command->info("Created campuses for tenant: {$tenant->name}");
        }
    }
}
