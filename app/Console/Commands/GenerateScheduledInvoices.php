<?php

namespace App\Console\Commands;

use App\Models\ChildEnrollment;
use App\Services\ChildEnrollmentInvoiceService;
use App\Enums\ChildEnrollmentStatus;
use App\Enums\ChildEnrollmentBilledEvery;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateScheduledInvoices extends Command
{
    protected $signature = 'invoices:generate-scheduled
                          {--days-ahead=7 : How many days ahead to generate invoices}
                          {--enrollment-id= : Generate for specific enrollment ID}
                          {--tenant-id= : Generate for specific tenant ID}
                          {--dry-run : Show what invoices would be generated without creating them}';

    protected $description = 'Generate scheduled invoices for child enrollments';

    public function handle(ChildEnrollmentInvoiceService $invoiceService): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $enrollmentId = $this->option('enrollment-id');
        $tenantId = $this->option('tenant-id');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE: No invoices will be created');
        }
        
        $this->info('Starting invoice generation...');
        
        // Include enrollments that may not be ACTIVE yet but need to be activated when invoiced
        $allowedStatuses = [
            ChildEnrollmentStatus::ACTIVE,
            ChildEnrollmentStatus::DRAFT,
            ChildEnrollmentStatus::PENDING,
            ChildEnrollmentStatus::INACTIVE
        ];
        
        $query = ChildEnrollment::whereIn('status', $allowedStatuses)
            ->with(['child.users', 'centre', 'product']);
        
        if ($enrollmentId) {
            $query->where('id', $enrollmentId);
        }
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $enrollments = $query->get();
        
        // Filter enrollments that need invoicing using service logic
        $enrollmentsNeedingInvoices = $enrollments->filter(function ($enrollment) use ($daysAhead, $invoiceService) {
            return $invoiceService->shouldGenerateInvoices($enrollment, $daysAhead);
        });
        
        if ($enrollmentsNeedingInvoices->isEmpty()) {
            $this->info('No enrollments need invoicing at this time.');
            return 0;
        }
        
        $this->info("Found {$enrollmentsNeedingInvoices->count()} enrollment(s) that need invoicing...");
        
        if ($dryRun) {
            $this->info('📋 DRY RUN - Showing what would be generated:');
            
            // Show what would be generated without actually creating invoices
            $groupedEnrollments = $this->groupEnrollmentsByParentAndCentre($enrollmentsNeedingInvoices);
            $invoiceCount = 0;
            
            foreach ($groupedEnrollments as $group) {
                $invoiceCount++;
                $parent = $group['parent'];
                $centre = $group['centre'];
                $children = $group['enrollments']->map(function ($enrollment) {
                    return $enrollment->child->full_name;
                })->join(', ');
                
                $this->info("  - Would create Invoice #{$invoiceCount} for {$parent->name} at {$centre->name} (Children: {$children})");
                
                // Show billing details for each enrollment
                foreach ($group['enrollments'] as $enrollment) {
                    $nextBillingDate = $invoiceService->getNextBillingPeriodStart($enrollment);
                    $this->line("    • {$enrollment->child->full_name}: {$enrollment->product->name} (Next billing: {$nextBillingDate->format('M j, Y')})");
                }
            }
            
            $this->info("📊 Total invoices that would be generated: {$invoiceCount}");
            return 0;
        }
        
        try {
            $invoices = $invoiceService->generateInvoicesForEnrollments($enrollmentsNeedingInvoices);
            $totalGenerated = $invoices->count();
            
            $this->info("Successfully generated {$totalGenerated} invoice(s)");
            
            // Show summary by parent and centre
            $invoices->each(function ($invoice) {
                $parent = $invoice->user;
                $centre = $invoice->centre;
                $children = $invoice->getChildren();
                $childNames = $children->pluck('full_name')->join(', ');
                
                $this->info("  - Invoice #{$invoice->number} for {$parent->name} at {$centre->name} (Children: {$childNames})");
            });
            
        } catch (\Exception $e) {
            $this->error("Failed to generate invoices: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function groupEnrollmentsByParentAndCentre($enrollments): array
    {
        $grouped = [];
        
        foreach ($enrollments as $enrollment) {
            // Get the parent/guardian user
            $parent = $enrollment->child->users()->first();
            if (!$parent) {
                continue; // Skip if no parent found
            }
            
            // Group by tenant_id + user_id + centre_id
            $groupKey = $enrollment->tenant_id . '_' . $parent->id . '_' . $enrollment->centre_id;
            
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'parent' => $parent,
                    'centre' => $enrollment->centre,
                    'tenant_id' => $enrollment->tenant_id,
                    'enrollments' => collect(),
                ];
            }
            
            $grouped[$groupKey]['enrollments']->push($enrollment);
        }
        
        return $grouped;
    }
}
