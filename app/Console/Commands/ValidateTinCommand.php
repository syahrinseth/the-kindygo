<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ValidateTinCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'einvoice:validate-tin {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate TIN configuration for e-Invoice submission';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 E-Invoice TIN Validation');
        $this->newLine();

        // Check global config TIN
        $this->info('📋 Global Configuration:');
        $globalTin = config('einvoice.supplier_tin');
        $myInvoisTin = config('einvoice.myinvois_tin');

        if ($globalTin) {
            $this->line("  ✅ Supplier TIN: {$globalTin}");
        } else {
            $this->error('  ❌ Supplier TIN: Not configured');
        }

        if ($myInvoisTin) {
            $this->line("  ✅ MyInvois TIN: {$myInvoisTin}");
        } else {
            $this->error('  ❌ MyInvois TIN: Not configured');
        }

        if ($globalTin && $myInvoisTin && $globalTin !== $myInvoisTin) {
            $this->warn("  ⚠️  WARNING: Supplier TIN and MyInvois TIN don't match!");
        }

        $this->newLine();

        // Check tenant-specific TINs
        $this->info('🏢 Tenant Configuration:');

        $tenantId = $this->option('tenant');
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");

                return 1;
            }
            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::all();
        }

        if ($tenants->isEmpty()) {
            $this->warn('  ⚠️  No tenants found in the system.');

            return 0;
        }

        foreach ($tenants as $tenant) {
            $this->line("  📂 Tenant: {$tenant->name} (ID: {$tenant->id})");

            if ($tenant->tax_identification_number) {
                $this->line("    ✅ TIN: {$tenant->tax_identification_number}");
            } else {
                $this->error("    ❌ TIN: Not configured (will use global: {$globalTin})");
            }

            if ($tenant->business_registration_number) {
                $this->line("    ✅ Business Registration: {$tenant->business_registration_number}");
            } else {
                $this->warn('    ⚠️  Business Registration: Not configured');
            }

            if ($tenant->business_activity_code) {
                $this->line("    ✅ Business Activity Code: {$tenant->business_activity_code}");
            } else {
                $this->warn('    ⚠️  Business Activity Code: Not configured (will use default: '.config('einvoice.supplier_business_activity_code', '85100').')');
            }

            if ($tenant->country) {
                $this->line("    ✅ Country: {$tenant->country}");
            } else {
                $this->warn('    ⚠️  Country: Not configured (will use default: '.config('einvoice.supplier_country', 'MY').')');
            }

            if ($tenant->state_code) {
                $this->line("    ✅ State Code: {$tenant->state_code}");
            } else {
                $this->warn('    ⚠️  State Code: Not configured (will use default: '.config('einvoice.default_state_code', '14').')');
            }

            $this->newLine();
        }

        // Summary and recommendations
        $this->info('📊 Summary & Recommendations:');

        $tenantsWithoutTin = $tenants->whereNull('tax_identification_number')->count();
        $tenantsWithoutRegNum = $tenants->whereNull('business_registration_number')->count();

        if ($tenantsWithoutTin > 0) {
            $this->warn("  ⚠️  {$tenantsWithoutTin} tenant(s) missing TIN - they will use global config");
            $this->line('     Consider updating tenant business information in admin panel');
        }

        if ($tenantsWithoutRegNum > 0) {
            $this->warn("  ⚠️  {$tenantsWithoutRegNum} tenant(s) missing business registration number");
        }

        if (! $globalTin) {
            $this->error('  ❌ Global TIN not configured - e-Invoice submission will fail');
            $this->line('     Set EINVOICE_SUPPLIER_TIN in your .env file');
        }

        if ($tenantsWithoutTin === 0 && $globalTin) {
            $this->info('  ✅ All tenants have proper TIN configuration');
        }

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('  • Each tenant should have their own TIN for proper e-Invoice authentication');
        $this->line('  • Supplier TIN in documents must match the TIN used for MyInvois authentication');
        $this->line('  • Update tenant business information in: Admin Panel → Tenants → Business Information');

        return 0;
    }
}
