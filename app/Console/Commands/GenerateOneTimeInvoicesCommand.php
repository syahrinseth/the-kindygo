<?php

namespace App\Console\Commands;

use App\Services\ChildEnrollmentService;
use App\Models\ChildEnrollment;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;

class GenerateOneTimeInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:generate-onetime 
                            {--tenant= : Specific tenant ID to process (leave empty for all tenants)}
                            {--dry-run : Run without actually creating invoices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate one-time invoices for enrollments with one-time billing';

    /**
     * The child enrollment service instance.
     *
     * @var ChildEnrollmentService
     */
    protected $enrollmentService;

    /**
     * Create a new command instance.
     *
     * @param ChildEnrollmentService $enrollmentService
     */
    public function __construct(ChildEnrollmentService $enrollmentService)
    {
        parent::__construct();
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting one-time invoice generation...');
        
        // Get the tenant ID if specified
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            $this->info("Processing one-time invoices for tenant ID: {$tenantId}");
        } else {
            $this->info("Processing one-time invoices for ALL TENANTS");
        }
        
        // Check if this is a dry run
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No invoices will be created');
            return $this->performDryRun($tenantId);
        }
        
        try {
            // Generate the one-time invoices without tenant scope
            $query = ChildEnrollment::active()
                ->current()
                ->where('billed_every', \App\Enums\ChildEnrollmentBilledEvery::ONE_TIME)
                ->with(['child.users', 'centre', 'product']);
                
            // Filter by tenant ID if specified
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
                
            $enrollments = $query->get();
                
            $results = $this->enrollmentService->generateOneTimeInvoices($enrollments);
            
            // Display results
            $this->displayResults($results);
            
            // Return appropriate exit code
            return empty($results['errors']) ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error("Failed to generate one-time invoices: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Perform a dry run to show what would be processed.
     *
     * @param int|null $tenantId
     * @return int
     */
    protected function performDryRun(?int $tenantId = null): int
    {
        // Get one-time enrollments that would be processed without tenant scope
        $query = ChildEnrollment::active()
            ->current()
            ->where('billed_every', \App\Enums\ChildEnrollmentBilledEvery::ONE_TIME)
            ->with(['child.users', 'centre', 'product']);
            
        // Filter by tenant ID if specified
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
            
        $enrollments = $query->get();
        
        if ($tenantId) {
            $this->info("One-time enrollments for tenant {$tenantId} that would be processed: {$enrollments->count()}");
        } else {
            $this->info("One-time enrollments (all tenants) that would be processed: {$enrollments->count()}");
        }
        
        if ($enrollments->count() > 0) {
            $this->table(
                ['Child', 'Product', 'Centre', 'Parent', 'Start Date', 'Tenant ID'],
                $enrollments->map(function ($enrollment) {
                    $primaryUser = $enrollment->child->users->first();
                    return [
                        $enrollment->child->first_name . ' ' . $enrollment->child->last_name,
                        $enrollment->product->name,
                        $enrollment->centre->name,
                        $primaryUser ? $primaryUser->name : 'No Parent',
                        $enrollment->date_start->toDateString(),
                        $enrollment->tenant_id,
                    ];
                })->toArray()
            );
        }
        
        // Group them to show consolidated invoices
        $groupedEnrollments = $this->enrollmentService->groupEnrollmentsForInvoicing($enrollments);
        
        $this->info("Number of invoices that would be created: " . count($groupedEnrollments));
        
        if (count($groupedEnrollments) > 0) {
            $this->info("Invoice groups:");
            foreach ($groupedEnrollments as $groupKey => $group) {
                $this->line("- Parent: {$group['user']->name}, Centre: {$group['centre']->name}, Enrollments: " . count($group['enrollments']) . ", Tenant: {$group['tenant_id']}");
            }
        }
        
        return 0;
    }

    /**
     * Display the results of invoice generation.
     *
     * @param array $results
     */
    protected function displayResults(array $results): void
    {
        // Summary
        $this->info('One-Time Invoice Generation Summary:');
        $this->line("Total enrollments processed: {$results['total_processed']}");
        $this->line("Invoices created: {$results['invoices_created']}");
        
        // Errors
        if (!empty($results['errors'])) {
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("- {$error}");
            }
        }
        
        // Success message
        if (empty($results['errors'])) {
            $this->info('✅ One-time invoice generation completed successfully!');
        } else {
            $this->warn('⚠️ One-time invoice generation completed with errors.');
        }
    }
}
