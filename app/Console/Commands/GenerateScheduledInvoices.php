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
                          {--tenant-id= : Generate for specific tenant ID}';

    protected $description = 'Generate scheduled invoices for child enrollments';

    public function handle(ChildEnrollmentInvoiceService $invoiceService): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $enrollmentId = $this->option('enrollment-id');
        $tenantId = $this->option('tenant-id');
        
        $this->info('Starting invoice generation...');
        
        $query = ChildEnrollment::where('status', ChildEnrollmentStatus::ACTIVE)
            ->with(['child.users', 'centre', 'product']);
        
        if ($enrollmentId) {
            $query->where('id', $enrollmentId);
        }
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        $enrollments = $query->get();
        
        // Filter enrollments that need invoicing
        $enrollmentsNeedingInvoices = $enrollments->filter(function ($enrollment) use ($daysAhead) {
            return $this->shouldGenerateInvoices($enrollment, $daysAhead);
        });
        
        if ($enrollmentsNeedingInvoices->isEmpty()) {
            $this->info('No enrollments need invoicing at this time.');
            return 0;
        }
        
        $this->info("Found {$enrollmentsNeedingInvoices->count()} enrollment(s) that need invoicing...");
        
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
    
    private function shouldGenerateInvoices(ChildEnrollment $enrollment, int $daysAhead): bool
    {
        // Check if we need to generate invoices based on the next billing date
        $nextBillingDate = $this->getNextBillingDate($enrollment);
        
        if (!$nextBillingDate) {
            return false;
        }
        
        $generateFromDate = Carbon::now()->addDays($daysAhead);
        
        return $nextBillingDate->lte($generateFromDate);
    }
    
    private function getNextBillingDate(ChildEnrollment $enrollment): ?Carbon
    {
        // Check if enrollment is one-time billing
        if ($enrollment->billed_every === ChildEnrollmentBilledEvery::ONE_TIME) {
            // Check if invoice item already exists for this enrollment
            $existingItem = $enrollment->invoiceItems()->first();
            return $existingItem ? null : $enrollment->date_start;
        }
        
        // For recurring billing, find the last invoice item and calculate next billing date
        $lastItem = $enrollment->invoiceItems()->orderBy('period_start', 'desc')->first();
        
        if (!$lastItem) {
            return $enrollment->date_start;
        }
        
        // Calculate next billing date based on billing frequency
        return match ($enrollment->billed_every) {
            ChildEnrollmentBilledEvery::DAILY => $lastItem->period_start->addDay(),
            ChildEnrollmentBilledEvery::WEEKLY => $lastItem->period_start->addWeek(),
            ChildEnrollmentBilledEvery::MONTHLY => $lastItem->period_start->addMonth(),
            ChildEnrollmentBilledEvery::QUARTERLY => $lastItem->period_start->addMonths(3),
            ChildEnrollmentBilledEvery::YEARLY => $lastItem->period_start->addYear(),
            default => null,
        };
    }
}
