<?php

namespace App\Console\Commands;

use App\Services\ChildEnrollmentService;
use App\Models\ChildEnrollment;
use App\Models\Tenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateRecurringInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:generate-recurring 
                            {--date= : Specific date to generate invoices for (Y-m-d format)}
                            {--effective-date= : Effective date for the generated invoices (Y-m-d format)}
                            {--tenant= : Specific tenant ID to process (leave empty for all tenants)}
                            {--dry-run : Run without actually creating invoices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring invoices based on active child enrollments';

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
        $this->info('Starting recurring invoice generation...');
        
        // Get the date to process
        $dateInput = $this->option('date');
        $forDate = $dateInput ? Carbon::parse($dateInput) : now();
        
        // Get the effective date for invoices (defaults to forDate if not specified)
        $effectiveDateInput = $this->option('effective-date');
        $effectiveDate = $effectiveDateInput ? Carbon::parse($effectiveDateInput) : $forDate;
        
        // Get the tenant ID if specified
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            $this->info("Processing invoices for tenant ID: {$tenantId} on date: {$forDate->toDateString()}");
        } else {
            $this->info("Processing invoices for ALL TENANTS on date: {$forDate->toDateString()}");
        }
        
        if ($effectiveDate->toDateString() !== $forDate->toDateString()) {
            $this->info("Invoice effective date: {$effectiveDate->toDateString()}");
        }
        
        // Check if this is a dry run
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No invoices will be created');
            return $this->performDryRun($forDate, $effectiveDate, $tenantId);
        }
        
        try {
            // Generate the recurring invoices with tenant scope disabled
            $results = $this->generateRecurringInvoicesWithoutTenantScope($forDate, $effectiveDate, $tenantId);
            
            // Display results
            $this->displayResults($results);
            
            // Return appropriate exit code
            return empty($results['errors']) ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error("Failed to generate recurring invoices: {$e->getMessage()}");
            return 1;
        }
    }
    
    /**
     * Generate recurring invoices without tenant scope restrictions.
     *
     * @param Carbon $forDate
     * @param Carbon $effectiveDate
     * @param int|null $tenantId
     * @return array
     */
    protected function generateRecurringInvoicesWithoutTenantScope(Carbon $forDate, Carbon $effectiveDate, ?int $tenantId = null): array
    {
        $results = [
            'total_processed' => 0,
            'invoices_created' => 0,
            'errors' => [],
            'skipped' => [],
            'by_frequency' => []
        ];

        if ($tenantId) {
            // Process single tenant
            return $this->processTenant($tenantId, $forDate, $effectiveDate);
        } else {
            // Process all tenants one by one for safety
            $tenants = Tenant::all();
            $this->info("Found {$tenants->count()} tenants to process");
            
            foreach ($tenants as $tenant) {
                $this->info("Processing tenant: {$tenant->id} - {$tenant->name}");
                
                try {
                    $tenantResults = $this->processTenant($tenant->id, $forDate, $effectiveDate);
                    
                    // Merge results
                    $results['total_processed'] += $tenantResults['total_processed'];
                    $results['invoices_created'] += $tenantResults['invoices_created'];
                    $results['errors'] = array_merge($results['errors'], $tenantResults['errors']);
                    $results['skipped'] = array_merge($results['skipped'], $tenantResults['skipped']);
                    
                    foreach ($tenantResults['by_frequency'] as $freq => $count) {
                        $results['by_frequency'][$freq] = ($results['by_frequency'][$freq] ?? 0) + $count;
                    }
                    
                    if ($tenantResults['invoices_created'] > 0) {
                        $this->info("✅ Tenant {$tenant->id}: {$tenantResults['invoices_created']} invoices created from {$tenantResults['total_processed']} enrollments");
                    } else {
                        $this->line("ℹ️ Tenant {$tenant->id}: No invoices needed (processed {$tenantResults['total_processed']} enrollments)");
                    }
                    
                } catch (\Exception $e) {
                    $this->error("❌ Failed to process tenant {$tenant->id}: {$e->getMessage()}");
                    $results['errors'][] = "Tenant {$tenant->id}: " . $e->getMessage();
                }
            }
        }

        return $results;
    }

    /**
     * Process invoices for a specific tenant with proper scope isolation.
     *
     * @param int $tenantId
     * @param Carbon $forDate
     * @param Carbon $effectiveDate
     * @return array
     */
    protected function processTenant(int $tenantId, Carbon $forDate, Carbon $effectiveDate): array
    {
        $results = [
            'total_processed' => 0,
            'invoices_created' => 0,
            'errors' => [],
            'skipped' => [],
            'by_frequency' => []
        ];

        try {
            // Process with tenant context - safer approach
            $enrollments = ChildEnrollment::where('tenant_id', $tenantId)
                ->active()
                ->current()
                ->with(['child.users', 'centre', 'product'])
                ->get()
                ->filter(function ($enrollment) use ($forDate) {
                    return $this->isEnrollmentDueForInvoicing($enrollment, $forDate);
                });

            $results['total_processed'] = $enrollments->count();

            if ($enrollments->count() > 0) {
                // Group enrollments - they're all from same tenant now
                $groupedEnrollments = $this->enrollmentService->groupEnrollmentsForInvoicing($enrollments);
                
                foreach ($groupedEnrollments as $groupKey => $group) {
                    try {
                        // Verify group belongs to correct tenant (safety check)
                        $this->validateTenantConsistency($group, $tenantId);

                        $invoice = $this->enrollmentService->createInvoiceForEnrollmentGroup($group, $forDate, $effectiveDate);
                        if ($invoice) {
                            $results['invoices_created']++;
                            
                            // Track by frequency
                            foreach ($group['enrollments'] as $enrollment) {
                                $frequency = $enrollment->billed_every->value;
                                $results['by_frequency'][$frequency] = ($results['by_frequency'][$frequency] ?? 0) + 1;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->error("Failed to create invoice for group {$groupKey} in tenant {$tenantId}: {$e->getMessage()}");
                        $results['errors'][] = "Tenant {$tenantId} - Group {$groupKey}: " . $e->getMessage();
                    }
                }
            }

        } catch (\Exception $e) {
            $this->error("Failed to process tenant {$tenantId}: {$e->getMessage()}");
            $results['errors'][] = "Tenant {$tenantId}: " . $e->getMessage();
        }

        return $results;
    }

    /**
     * Validate that all data in a group belongs to the same tenant.
     *
     * @param array $group
     * @param int $expectedTenantId
     * @throws \Exception
     */
    protected function validateTenantConsistency(array $group, int $expectedTenantId): void
    {
        // Check group tenant ID
        if ($group['tenant_id'] !== $expectedTenantId) {
            throw new \Exception("Group tenant mismatch: expected {$expectedTenantId}, got {$group['tenant_id']}");
        }
        
        // Check all enrollments belong to the same tenant
        foreach ($group['enrollments'] as $enrollment) {
            if ($enrollment->tenant_id !== $expectedTenantId) {
                throw new \Exception("Enrollment tenant mismatch: expected {$expectedTenantId}, got {$enrollment->tenant_id}");
            }
        }
        
        // Check centre belongs to the same tenant (if centre has tenant_id)
        if (isset($group['centre']->tenant_id) && $group['centre']->tenant_id !== $expectedTenantId) {
            throw new \Exception("Centre tenant mismatch: expected {$expectedTenantId}, got {$group['centre']->tenant_id}");
        }
    }
    
    /**
     * Check if an enrollment is due for invoicing (replicated from service for command use).
     *
     * @param ChildEnrollment $enrollment
     * @param Carbon $forDate
     * @return bool
     */
    protected function isEnrollmentDueForInvoicing($enrollment, Carbon $forDate): bool
    {
        // Skip one-time billing enrollments
        if ($enrollment->billed_every === \App\Enums\ChildEnrollmentBilledEvery::ONE_TIME) {
            return false;
        }

        $startDate = $enrollment->date_start->startOfDay();
        $checkDate = $forDate->copy()->startOfDay();

        // Check if we've passed the start date
        if ($checkDate->lt($startDate)) {
            return false;
        }

        // Check if enrollment has ended
        if ($enrollment->date_end && $checkDate->gt($enrollment->date_end->startOfDay())) {
            return false;
        }
        
        // Calculate if this date matches the billing cycle
        return $this->matchesBillingCycle($enrollment, $forDate);
    }
    
    /**
     * Check if the given date matches the enrollment's billing cycle (replicated from service for command use).
     *
     * @param ChildEnrollment $enrollment
     * @param Carbon $forDate
     * @return bool
     */
    protected function matchesBillingCycle($enrollment, Carbon $forDate): bool
    {
        $startDate = $enrollment->date_start->startOfDay();
        $checkDate = $forDate->copy()->startOfDay();
        
        switch ($enrollment->billed_every) {
            case \App\Enums\ChildEnrollmentBilledEvery::DAILY:
                return true;
                
            case \App\Enums\ChildEnrollmentBilledEvery::WEEKLY:
                return $checkDate->dayOfWeek === $startDate->dayOfWeek &&
                       $checkDate->diffInWeeks($startDate) >= 0;
                       
            case \App\Enums\ChildEnrollmentBilledEvery::MONTHLY:
                $targetDay = min($startDate->day, $checkDate->daysInMonth);
                return $checkDate->day === $targetDay &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case \App\Enums\ChildEnrollmentBilledEvery::QUARTERLY:
                return $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInMonths($startDate) % 3 === 0 &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case \App\Enums\ChildEnrollmentBilledEvery::YEARLY:
                return $checkDate->month === $startDate->month &&
                       $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInYears($startDate) >= 0;
                       
            default:
                return false;
        }
    }
    
    /**
     * Check if an additional product is due for invoicing on the given date.
     *
     * @param array $additionalProduct
     * @param Carbon $forDate
     * @param ChildEnrollment $enrollment
     * @return bool
     */
    protected function isAdditionalProductDueForInvoicing(array $additionalProduct, Carbon $forDate, ChildEnrollment $enrollment): bool
    {
        // Skip one-time billing additional products
        $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
        if ($billedEvery === \App\Enums\ChildEnrollmentBilledEvery::ONE_TIME->value) {
            return false;
        }

        // Get start date for additional product or fall back to enrollment start date
        $startDate = isset($additionalProduct['date_start']) 
            ? Carbon::parse($additionalProduct['date_start'])->startOfDay()
            : $enrollment->date_start->startOfDay();
        
        $checkDate = $forDate->copy()->startOfDay();

        // Check if we've passed the start date
        if ($checkDate->lt($startDate)) {
            return false;
        }

        // Check if additional product has ended
        if (isset($additionalProduct['date_end']) && $additionalProduct['date_end']) {
            $endDate = Carbon::parse($additionalProduct['date_end'])->startOfDay();
            if ($checkDate->gt($endDate)) {
                return false;
            }
        }
        
        // Calculate if this date matches the billing cycle
        return $this->matchesAdditionalProductBillingCycle($additionalProduct, $forDate, $startDate);
    }

    /**
     * Check if the given date matches the additional product's billing cycle.
     *
     * @param array $additionalProduct
     * @param Carbon $forDate
     * @param Carbon $startDate
     * @return bool
     */
    protected function matchesAdditionalProductBillingCycle(array $additionalProduct, Carbon $forDate, Carbon $startDate): bool
    {
        $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
        $checkDate = $forDate->copy()->startOfDay();
        
        switch ($billedEvery) {
            case \App\Enums\ChildEnrollmentBilledEvery::DAILY->value:
                return true;
                
            case \App\Enums\ChildEnrollmentBilledEvery::WEEKLY->value:
                return $checkDate->dayOfWeek === $startDate->dayOfWeek &&
                       $checkDate->diffInWeeks($startDate) >= 0;
                       
            case \App\Enums\ChildEnrollmentBilledEvery::MONTHLY->value:
                $targetDay = min($startDate->day, $checkDate->daysInMonth);
                return $checkDate->day === $targetDay &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case \App\Enums\ChildEnrollmentBilledEvery::QUARTERLY->value:
                return $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInMonths($startDate) % 3 === 0 &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case \App\Enums\ChildEnrollmentBilledEvery::YEARLY->value:
                return $checkDate->month === $startDate->month &&
                       $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInYears($startDate) >= 0;
                       
            default:
                return false;
        }
    }
    
    /**
     * Perform a dry run to show what would be processed.
     *
     * @param Carbon $forDate
     * @param Carbon $effectiveDate
     * @param int|null $tenantId
     * @return int
     */
    protected function performDryRun(Carbon $forDate, Carbon $effectiveDate, ?int $tenantId = null): int
    {
        // Get enrollments that would be processed without tenant scope
        $query = ChildEnrollment::active()
            ->current()
            ->with(['child.users', 'centre', 'product']);
        
        // Filter by tenant ID if specified
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $enrollments = $query->get()
            ->filter(function ($enrollment) use ($forDate) {
                return $this->isEnrollmentDueForInvoicing($enrollment, $forDate);
            });
        
        if ($tenantId) {
            $this->info("Enrollments for tenant {$tenantId} that would be processed: {$enrollments->count()}");
        } else {
            $this->info("Enrollments (all tenants) that would be processed: {$enrollments->count()}");
        }
        
        if ($effectiveDate->toDateString() !== $forDate->toDateString()) {
            $this->info("Note: Invoice effective date would be: {$effectiveDate->toDateString()}");
        }
        
        if ($enrollments->count() > 0) {
            $enrollmentData = [];
            foreach ($enrollments as $enrollment) {
                $primaryUser = $enrollment->child?->users?->first();
                
                // Main enrollment
                $enrollmentData[] = [
                    $enrollment->child->first_name . ' ' . $enrollment->child->last_name,
                    $enrollment->product->name,
                    $enrollment->centre->name,
                    $primaryUser ? $primaryUser->name : 'No Parent',
                    $enrollment->billed_every->value,
                    $enrollment->date_start->toDateString(),
                    $enrollment->tenant_id,
                    'Main Product'
                ];
                
                // Additional products that would be billed
                $additionalProducts = $enrollment->additional_products ?? [];
                foreach ($additionalProducts as $additionalProduct) {
                    if (!isset($additionalProduct['product_id'])) {
                        continue;
                    }
                    
                    // Check if this additional product would be billed
                    if ($this->isAdditionalProductDueForInvoicing($additionalProduct, $forDate, $enrollment)) {
                        $product = \App\Models\Product::find($additionalProduct['product_id']);
                        if ($product) {
                            $billingFreq = $additionalProduct['billed_every'] ?? 'monthly';
                            $enrollmentData[] = [
                                $enrollment->child->first_name . ' ' . $enrollment->child->last_name,
                                $product->name,
                                $enrollment->centre->name,
                                $primaryUser ? $primaryUser->name : 'No Parent',
                                $billingFreq,
                                isset($additionalProduct['date_start']) ? 
                                    \Carbon\Carbon::parse($additionalProduct['date_start'])->toDateString() : 
                                    $enrollment->date_start->toDateString(),
                                $enrollment->tenant_id,
                                'Additional Product'
                            ];
                        }
                    }
                }
            }
            
            $this->table(
                ['Child', 'Product', 'Centre', 'Parent', 'Billing Frequency', 'Start Date', 'Tenant ID', 'Type'],
                $enrollmentData
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
        $this->info('Invoice Generation Summary:');
        $this->line("Total enrollments processed: {$results['total_processed']}");
        $this->line("Invoices created: {$results['invoices_created']}");
        
        // Breakdown by frequency
        if (!empty($results['by_frequency'])) {
            $this->info('Breakdown by billing frequency:');
            foreach ($results['by_frequency'] as $frequency => $count) {
                $this->line("- {$frequency}: {$count} enrollments");
            }
        }
        
        // Errors
        if (!empty($results['errors'])) {
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("- {$error}");
            }
        }
        
        // Skipped items
        if (!empty($results['skipped'])) {
            $this->warn('Skipped items:');
            foreach ($results['skipped'] as $skipped) {
                $this->line("- {$skipped}");
            }
        }
        
        // Success message
        if (empty($results['errors'])) {
            $this->info('✅ Invoice generation completed successfully!');
        } else {
            $this->warn('⚠️ Invoice generation completed with errors.');
        }
    }
}
