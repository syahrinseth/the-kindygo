<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MarkOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:mark-overdue 
                          {--dry-run : Show what would be updated without actually updating}
                          {--tenant= : Process invoices for specific tenant ID only}
                          {--tenant-slug= : Process invoices for specific tenant slug only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark pending invoices as overdue when past due date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');
        $tenantSlug = $this->option('tenant-slug');

        // Validate and resolve tenant
        $tenant = $this->resolveTenant($tenantId, $tenantSlug);

        $this->info('Checking for overdue invoices...');

        if ($tenant) {
            $this->info("Processing invoices for tenant: {$tenant->name} (ID: {$tenant->id})");
        } else {
            $this->info('Processing invoices for ALL tenants');
        }

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No invoices will be updated');
        }

        // Find pending invoices that are past due
        $overdueQuery = Invoice::where('status', InvoiceStatus::PENDING)
            ->where('due_at', '<', now())
            ->with(['user', 'centre']);

        // Filter by tenant if specified
        if ($tenant) {
            $overdueQuery->where('tenant_id', $tenant->id);
        }

        $overdueInvoices = $overdueQuery->get();

        $updateCount = 0;

        if ($overdueInvoices->isEmpty()) {
            $this->info('No overdue invoices found.');

            return Command::SUCCESS;
        }

        foreach ($overdueInvoices as $invoice) {
            try {
                $daysPastDue = $invoice->due_at->diffInDays(now());

                if ($isDryRun) {
                    $this->line("Would mark invoice #{$invoice->number} as overdue ({$daysPastDue} days past due)");
                } else {
                    $invoice->update(['status' => InvoiceStatus::OVERDUE]);
                    $this->line("Marked invoice #{$invoice->number} as overdue ({$daysPastDue} days past due)");
                }

                $updateCount++;

            } catch (Exception $e) {
                $this->error("Failed to update invoice #{$invoice->number}: ".$e->getMessage());

                Log::error('Failed to mark invoice as overdue', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Process completed. Invoices processed: {$updateCount}");

        if (! $isDryRun && $updateCount > 0) {
            Log::info('Marked invoices as overdue', [
                'tenant_id' => $tenant?->id,
                'tenant_name' => $tenant?->name,
                'count' => $updateCount,
                'processed_at' => now(),
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Resolve tenant from ID or slug
     */
    private function resolveTenant(?string $tenantId, ?string $tenantSlug): ?Tenant
    {
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant with ID '{$tenantId}' not found");
                exit(1);
            }

            return $tenant;
        }

        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
            if (! $tenant) {
                $this->error("Tenant with slug '{$tenantSlug}' not found");
                exit(1);
            }

            return $tenant;
        }

        return null;
    }
}
