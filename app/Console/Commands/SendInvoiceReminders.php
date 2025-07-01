<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Notifications\InvoicePendingNotification;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendInvoiceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:send-reminders 
                          {--dry-run : Show what would be sent without actually sending}
                          {--days-before=3 : Days before due date to send reminder}
                          {--overdue-only : Only send overdue notifications}
                          {--pending-only : Only send pending notifications}
                          {--tenant= : Process invoices for specific tenant ID only}
                          {--tenant-slug= : Process invoices for specific tenant slug only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated invoice payment reminders to users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $daysBefore = (int) $this->option('days-before');
        $overdueOnly = $this->option('overdue-only');
        $pendingOnly = $this->option('pending-only');
        $tenantId = $this->option('tenant');
        $tenantSlug = $this->option('tenant-slug');

        // Validate and resolve tenant
        $tenant = $this->resolveTenant($tenantId, $tenantSlug);
        
        $this->info('Starting invoice reminder process...');
        
        if ($tenant) {
            $this->info("Processing invoices for tenant: {$tenant->name} (ID: {$tenant->id})");
        } else {
            $this->info('Processing invoices for ALL tenants');
        }
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No emails will be sent');
        }

        // First, mark overdue invoices (unless dry-run)
        if (!$isDryRun) {
            $this->info('Marking overdue invoices...');
            $overdueMarked = $this->markOverdueInvoices($tenant);
            if ($overdueMarked > 0) {
                $this->line("Marked {$overdueMarked} invoices as overdue");
            }
        }

        $pendingCount = 0;
        $overdueCount = 0;
        $errorCount = 0;

        // Process overdue invoices (unless pending-only is specified)
        if (!$pendingOnly) {
            $this->info('Processing overdue invoices...');
            
            $overdueQuery = Invoice::where('status', InvoiceStatus::OVERDUE)
                ->with(['user', 'centre']);
                
            // Filter by tenant if specified
            if ($tenant) {
                $overdueQuery->where('tenant_id', $tenant->id);
            }
            
            $overdueInvoices = $overdueQuery->get();

            foreach ($overdueInvoices as $invoice) {
                try {
                    $daysOverdue = $invoice->due_at->diffInDays(now());
                    
                    if ($isDryRun) {
                        $this->line("Would send overdue notification to {$invoice->user->email} for invoice #{$invoice->number} ({$daysOverdue} days overdue)");
                    } else {
                        $invoice->user->notify(new InvoiceOverdueNotification($invoice, $daysOverdue));
                        $this->line("Sent overdue notification to {$invoice->user->email} for invoice #{$invoice->number}");
                    }
                    
                    $overdueCount++;
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Failed to send overdue notification for invoice #{$invoice->number}: " . $e->getMessage());
                    
                    Log::error('Failed to send automated overdue notification', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Process pending invoices approaching due date (unless overdue-only is specified)
        if (!$overdueOnly) {
            $this->info("Processing pending invoices due within {$daysBefore} days...");
            
            $reminderDate = now()->addDays($daysBefore);
            
            $pendingQuery = Invoice::where('status', InvoiceStatus::PENDING)
                ->whereDate('due_at', '<=', $reminderDate)
                ->whereDate('due_at', '>=', now())
                ->with(['user', 'centre']);
                
            // Filter by tenant if specified
            if ($tenant) {
                $pendingQuery->where('tenant_id', $tenant->id);
            }
            
            $pendingInvoices = $pendingQuery->get();

            foreach ($pendingInvoices as $invoice) {
                try {
                    if ($isDryRun) {
                        $this->line("Would send pending notification to {$invoice->user->email} for invoice #{$invoice->number} (due {$invoice->due_at->format('M d, Y')})");
                    } else {
                        $invoice->user->notify(new InvoicePendingNotification($invoice));
                        $this->line("Sent pending notification to {$invoice->user->email} for invoice #{$invoice->number}");
                    }
                    
                    $pendingCount++;
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("Failed to send pending notification for invoice #{$invoice->number}: " . $e->getMessage());
                    
                    Log::error('Failed to send automated pending notification', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Summary
        $this->newLine();
        $this->info('Invoice reminder process completed:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Pending Reminders', $pendingCount],
                ['Overdue Reminders', $overdueCount],
                ['Errors', $errorCount],
            ]
        );

        if (!$isDryRun) {
            Log::info('Automated invoice reminders sent', [
                'tenant_id' => $tenant?->id,
                'tenant_name' => $tenant?->name,
                'pending_count' => $pendingCount,
                'overdue_count' => $overdueCount,
                'error_count' => $errorCount,
                'total_sent' => $pendingCount + $overdueCount,
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
            if (!$tenant) {
                $this->error("Tenant with ID '{$tenantId}' not found");
                exit(1);
            }
            return $tenant;
        }
        
        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
            if (!$tenant) {
                $this->error("Tenant with slug '{$tenantSlug}' not found");
                exit(1);
            }
            return $tenant;
        }
        
        return null;
    }

    /**
     * Mark pending invoices as overdue if past due date
     */
    private function markOverdueInvoices(?Tenant $tenant = null): int
    {
        $overdueQuery = Invoice::where('status', InvoiceStatus::PENDING)
            ->where('due_at', '<', now());
            
        // Filter by tenant if specified
        if ($tenant) {
            $overdueQuery->where('tenant_id', $tenant->id);
        }
        
        $overdueInvoices = $overdueQuery->get();

        $count = 0;
        foreach ($overdueInvoices as $invoice) {
            try {
                $invoice->update(['status' => InvoiceStatus::OVERDUE]);
                $count++;
            } catch (\Exception $e) {
                Log::error('Failed to mark invoice as overdue', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
