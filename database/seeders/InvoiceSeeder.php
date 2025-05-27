<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Centre;
use App\Models\Invoice;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create tenants
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            // Create 3 tenants
            $tenants = collect();
            for ($i = 0; $i < 3; $i++) {
                $tenants->push(Tenant::factory()->create());
            }
        }
        
        // For each tenant, ensure they have centres
        foreach ($tenants as $tenant) {
            // Get or create centres for this tenant
            $centres = Centre::where('tenant_id', $tenant->id)->get();
            
            if ($centres->isEmpty()) {
                // Create 2 centres for this tenant
                $centres = collect();
                for ($i = 0; $i < 2; $i++) {
                    $centres->push(
                        Centre::factory()->create([
                            'tenant_id' => $tenant->id,
                        ])
                    );
                }
            }
            
            // Get users or create some test users if none exist
            $users = User::all();
            if ($users->isEmpty()) {
                $users = User::factory(3)->create();
                
                // Associate users with tenant
                foreach ($users as $user) {
                    $tenant->users()->attach($user->id);
                }
            }
            
            // Create invoices for each centre and user
            foreach ($centres as $centre) {
                foreach ($users as $user) {
                    // Create one invoice in each status
                    foreach (InvoiceStatus::cases() as $status) {
                        Invoice::factory()->create([
                            'tenant_id' => $tenant->id,
                            'centre_id' => $centre->id,
                            'user_id' => $user->id,
                            'status' => $status,
                        ]);
                    }
                }
            }
        }
    }
}
