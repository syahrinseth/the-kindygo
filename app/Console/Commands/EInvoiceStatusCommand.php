<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\EInvoiceSDKService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class EInvoiceStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'einvoice:status 
                            {--check-config : Display detailed configuration}
                            {--tenant= : Check status for specific tenant ID}
                            {--tenant-slug= : Check status for specific tenant slug}
                            {--all-tenants : Check status for all tenants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check e-Invoice system status and configuration for specific tenant(s)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏥 KindyGo e-Invoice System Status Check');
        $this->newLine();

        // Determine which tenants to check
        $tenants = $this->getTenantsToCheck();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found to check.');

            return 1;
        }

        // Check each tenant
        foreach ($tenants as $tenant) {
            $this->checkTenantStatus($tenant);

            if ($tenants->count() > 1) {
                $this->newLine();
                $this->line('─────────────────────────────────────────────────');
                $this->newLine();
            }
        }

        $this->newLine();
        $this->info('✅ Status check completed!');

        return 0;
    }

    /**
     * Get the tenants to check based on command options.
     */
    private function getTenantsToCheck()
    {
        if ($tenantId = $this->option('tenant')) {
            return Tenant::where('id', $tenantId)->get();
        }

        if ($tenantSlug = $this->option('tenant-slug')) {
            return Tenant::where('slug', $tenantSlug)->get();
        }

        if ($this->option('all-tenants')) {
            return Tenant::all();
        }

        // Default: check the first tenant or ask user to specify
        $tenantsCount = Tenant::count();

        if ($tenantsCount === 0) {
            $this->error('No tenants found in the system.');

            return collect();
        }

        if ($tenantsCount === 1) {
            return Tenant::limit(1)->get();
        }

        // Multiple tenants available - ask user to specify
        $this->warn("Multiple tenants found ({$tenantsCount} total). Please specify which tenant to check:");
        $this->line('  --tenant=<ID>        Check specific tenant by ID');
        $this->line('  --tenant-slug=<slug> Check specific tenant by slug');
        $this->line('  --all-tenants        Check all tenants');
        $this->newLine();

        $this->info('Available tenants:');
        Tenant::select('id', 'name', 'slug')->get()->each(function ($tenant) {
            $this->line("  ID: {$tenant->id} | Slug: {$tenant->slug} | Name: {$tenant->name}");
        });

        return collect();
    }

    /**
     * Check status for a specific tenant.
     */
    private function checkTenantStatus(Tenant $tenant)
    {
        $this->info("🏢 Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->newLine();

        // Check tenant configuration
        $this->checkTenantConfiguration($tenant);

        // Check database
        $this->checkDatabase();

        // Check services
        $this->checkTenantServices($tenant);

        // Check tenant invoices
        $this->checkTenantInvoices($tenant);

        if ($this->option('check-config')) {
            $this->displayTenantConfiguration($tenant);
        }
    }

    private function checkTenantConfiguration(Tenant $tenant)
    {
        $this->info('📋 Tenant Configuration Check:');

        $configs = [
            'TIN' => $tenant->tax_identification_number ? '***configured***' : null,
            'Business ID Type' => $tenant->business_id_type,
            'Business ID Value' => $tenant->business_id_value ? '***configured***' : null,
            'Business Activity Code' => $tenant->business_activity_code,
            'Country' => $tenant->country,
            'State Code' => $tenant->state_code,
            'E-Invoice Client ID' => $tenant->einvoice_client_id ? '***configured***' : 'Using global config',
            'E-Invoice Client Secret' => $tenant->einvoice_client_secret ? '***configured***' : 'Using global config',
            'E-Invoice Environment' => $tenant->getEInvoiceEnvironment(),
        ];

        foreach ($configs as $key => $value) {
            if ($value === null) {
                $status = '❌';
                $displayValue = 'Not set';
            } elseif (str_contains($value, 'global config')) {
                $status = '⚠️';
                $displayValue = $value;
            } else {
                $status = '✅';
                $displayValue = $value;
            }

            $this->line("  {$status} {$key}: {$displayValue}");
        }

        // Additional validation checks
        $this->newLine();
        $this->line('  Validation Checks:');

        try {
            if ($tenant->tax_identification_number && ! preg_match('/^[A-Z0-9]{10,20}$/', $tenant->tax_identification_number)) {
                $this->line('  ❌ TIN format invalid (should be 10-20 alphanumeric characters)');
            } else {
                $this->line('  ✅ TIN format valid');
            }
        } catch (Exception $e) {
            $this->line('  ❌ TIN validation error: '.$e->getMessage());
        }

        if ($tenant->hasEInvoiceCredentials()) {
            $this->line('  ✅ Tenant has specific e-Invoice credentials');
        } else {
            $this->line('  ⚠️  Tenant using global e-Invoice credentials');
        }

        $this->newLine();
    }

    private function checkConfiguration()
    {
        $this->info('📋 Configuration Check:');

        $configs = [
            'base_url' => config('einvoice.base_url'),
            'environment' => config('einvoice.environment'),
            'supplier_tin' => config('einvoice.supplier_tin') ? '***configured***' : null,
            'client_id' => config('einvoice.client_id') ? '***configured***' : null,
            'client_secret' => config('einvoice.client_secret') ? '***configured***' : null,
        ];

        foreach ($configs as $key => $value) {
            $status = $value ? '✅' : '❌';
            $this->line("  {$status} {$key}: ".($value ?? 'Not set'));
        }

        $this->newLine();
    }

    private function checkDatabase()
    {
        $this->info('🗄️  Database Check:');

        try {
            // Check if invoices table has e-invoice columns
            $hasColumns = Schema::hasColumns('invoices', [
                'einvoice_uuid',
                'einvoice_submission_id',
                'einvoice_status',
                'einvoice_validation_url',
                'einvoice_submitted_at',
            ]);

            if ($hasColumns) {
                $this->line('  ✅ E-Invoice columns exist in invoices table');
            } else {
                $this->line('  ❌ E-Invoice columns missing - run migration');
            }

        } catch (Exception $e) {
            $this->line('  ❌ Database connection failed: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function checkTenantServices(Tenant $tenant)
    {
        $this->info('🔧 Services Check:');

        try {
            $service = new EInvoiceSDKService($tenant);
            $this->line('  ✅ EInvoiceSDKService: Available for tenant');

            // Test API connectivity if possible
            try {
                $isValid = $service->validateTaxpayerTinDirect();
                $this->line('  ✅ API Connection: TIN validation successful');
            } catch (Exception $e) {
                $this->line('  ⚠️  API Connection: '.$e->getMessage());
            }

        } catch (Exception $e) {
            $this->line('  ❌ EInvoiceSDKService: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function checkTenantInvoices(Tenant $tenant)
    {
        $this->info('📄 Tenant Invoice Statistics:');

        try {
            $totalInvoices = $tenant->invoices()->count();
            $submittedInvoices = $tenant->invoices()->whereNotNull('einvoice_uuid')->count();
            $validInvoices = $tenant->invoices()->where('einvoice_status', 'valid')->count();
            $pendingInvoices = $tenant->invoices()->where('einvoice_status', 'pending')->count();
            $failedInvoices = $tenant->invoices()->where('einvoice_status', 'invalid')->count();

            $this->line("  📊 Total invoices: {$totalInvoices}");
            $this->line("  📤 Submitted to e-Invoice: {$submittedInvoices}");
            $this->line("  ✅ Valid e-Invoices: {$validInvoices}");
            $this->line("  ⏳ Pending validation: {$pendingInvoices}");
            $this->line("  ❌ Failed/Invalid: {$failedInvoices}");

            if ($totalInvoices > 0) {
                $submissionRate = round(($submittedInvoices / $totalInvoices) * 100, 1);
                $this->line("  📈 Submission rate: {$submissionRate}%");

                if ($validInvoices > 0) {
                    $successRate = round(($validInvoices / $submittedInvoices) * 100, 1);
                    $this->line("  💯 Success rate: {$successRate}%");
                }
            }

            // Show recent submission activity
            $recentSubmissions = $tenant->invoices()
                ->whereNotNull('einvoice_submitted_at')
                ->where('einvoice_submitted_at', '>=', now()->subDays(7))
                ->count();

            $this->line("  📅 Submissions last 7 days: {$recentSubmissions}");

        } catch (Exception $e) {
            $this->line('  ❌ Could not retrieve invoice statistics: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function checkInvoices()
    {
        $this->info('📄 Invoice Statistics:');

        try {
            $totalInvoices = Invoice::count();
            $submittedInvoices = Invoice::whereNotNull('einvoice_uuid')->count();
            $validInvoices = Invoice::where('einvoice_status', 'valid')->count();
            $pendingInvoices = Invoice::where('einvoice_status', 'pending')->count();
            $failedInvoices = Invoice::where('einvoice_status', 'invalid')->count();

            $this->line("  📊 Total invoices: {$totalInvoices}");
            $this->line("  📤 Submitted to e-Invoice: {$submittedInvoices}");
            $this->line("  ✅ Valid e-Invoices: {$validInvoices}");
            $this->line("  ⏳ Pending validation: {$pendingInvoices}");
            $this->line("  ❌ Failed/Invalid: {$failedInvoices}");

            if ($totalInvoices > 0) {
                $submissionRate = round(($submittedInvoices / $totalInvoices) * 100, 1);
                $this->line("  📈 Submission rate: {$submissionRate}%");
            }

        } catch (Exception $e) {
            $this->line('  ❌ Could not retrieve invoice statistics: '.$e->getMessage());
        }

        $this->newLine();
    }

    private function displayTenantConfiguration(Tenant $tenant)
    {
        $this->info('🔧 Detailed Tenant Configuration:');

        $this->line('  Tenant Information:');
        $this->line("    Name: {$tenant->name}");
        $this->line("    Slug: {$tenant->slug}");
        $this->line("    ID: {$tenant->id}");
        $this->newLine();

        $this->line('  Business Information:');
        $this->line('    TIN: '.($tenant->tax_identification_number ?? 'Not set'));
        $this->line('    Business ID Type: '.($tenant->business_id_type ?? 'Not set'));
        $this->line('    Business ID Value: '.($tenant->business_id_value ? '***hidden***' : 'Not set'));
        $this->line('    Business Activity Code: '.($tenant->business_activity_code ?? 'Not set'));
        $this->line('    Business Activity Description: '.($tenant->business_activity_description ?? 'Not set'));
        $this->line('    Country: '.($tenant->country ?? 'Not set'));
        $this->line('    State Code: '.($tenant->state_code ?? 'Not set'));
        $this->newLine();

        $this->line('  E-Invoice API Configuration:');
        $this->line('    Client ID: '.($tenant->einvoice_client_id ? '***hidden***' : 'Using global config'));
        $this->line('    Client Secret: '.($tenant->einvoice_client_secret ? '***hidden***' : 'Using global config'));
        $this->line('    Environment: '.$tenant->getEInvoiceEnvironment());
        $this->line('    Has Specific Credentials: '.($tenant->hasEInvoiceCredentials() ? 'Yes' : 'No'));
        $this->line('    Is Production: '.($tenant->isEInvoiceProduction() ? 'Yes' : 'No'));
        $this->newLine();

        $this->line('  Global Fallback Configuration:');
        $config = config('einvoice');

        $importantKeys = [
            'base_url', 'client_id', 'client_secret', 'environment',
            'default_currency', 'default_country_code', 'supplier_tin',
        ];

        foreach ($importantKeys as $key) {
            $value = $config[$key] ?? null;

            if (in_array($key, ['client_id', 'client_secret', 'supplier_tin']) && $value) {
                $value = '***hidden***';
            }

            $this->line("    {$key}: ".($value ?? 'null'));
        }

        $this->newLine();
    }
}
