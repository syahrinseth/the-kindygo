<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing tenants (from UserSeeder)
        $tenants = Tenant::with('users')->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Please run UserSeeder first.');

            return;
        }

        // For each tenant, ensure they have centres and create invoices
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

            // Get users that belong to this tenant
            $users = $tenant->users;

            if ($users->isEmpty()) {
                $this->command->warn("No users found for tenant: {$tenant->name}");

                continue;
            }

            // Create invoices for each centre and user in this tenant
            foreach ($centres as $centre) {
                // Track invoice counter per centre to avoid unique constraint violations
                // The counter must be per centre because invoice numbers are unique per tenant+centre
                $invoiceCounter = 1;

                // Get centre code for invoice numbering
                $centreCode = Centre::generateCentreCode($centre);
                $year = now()->format('Y');

                foreach ($users as $user) {
                    // Create one invoice in each status
                    foreach (InvoiceStatus::cases() as $status) {
                        // Manually generate unique invoice number to avoid race conditions
                        $number = "KG{$centreCode}/{$year}/".str_pad($invoiceCounter++, 4, '0', STR_PAD_LEFT);

                        Invoice::factory()->create([
                            'number' => $number, // Explicitly set to avoid auto-generation
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
