<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\EInvoiceSDKService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class EInvoiceStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'einvoice:status {--check-config}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check e-Invoice system status and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏥 KindyGo e-Invoice System Status Check');
        $this->newLine();

        // Check configuration
        $this->checkConfiguration();
        
        // Check database
        $this->checkDatabase();
        
        // Check services
        $this->checkServices();
        
        // Check invoices
        $this->checkInvoices();
        
        if ($this->option('check-config')) {
            $this->displayConfiguration();
        }
        
        $this->newLine();
        $this->info('✅ Status check completed!');
        
        return 0;
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
            $this->line("  {$status} {$key}: " . ($value ?? 'Not set'));
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
                'einvoice_submitted_at'
            ]);
            
            if ($hasColumns) {
                $this->line('  ✅ E-Invoice columns exist in invoices table');
            } else {
                $this->line('  ❌ E-Invoice columns missing - run migration');
            }
            
        } catch (\Exception $e) {
            $this->line('  ❌ Database connection failed: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
    
    private function checkServices()
    {
        $this->info('🔧 Services Check:');
        
        try {
            $service = new EInvoiceSDKService();
            $this->line('  ✅ EInvoiceSDKService: Available');
        } catch (\Exception $e) {
            $this->line('  ❌ EInvoiceSDKService: ' . $e->getMessage());
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
            
        } catch (\Exception $e) {
            $this->line('  ❌ Could not retrieve invoice statistics: ' . $e->getMessage());
        }
        
        $this->newLine();
    }
    
    private function displayConfiguration()
    {
        $this->info('🔧 Detailed Configuration:');
        
        $config = config('einvoice');
        
        foreach ($config as $key => $value) {
            if (in_array($key, ['client_id', 'client_secret']) && $value) {
                $value = '***hidden***';
            }
            
            if (is_array($value)) {
                $this->line("  {$key}:");
                foreach ($value as $subKey => $subValue) {
                    $this->line("    {$subKey}: {$subValue}");
                }
            } else {
                $this->line("  {$key}: " . ($value ?? 'null'));
            }
        }
        
        $this->newLine();
    }
}
