<?php

namespace App\Console\Commands;

use App\Models\ChildEnrolment;
use App\Services\ChildEnrolmentInvoiceService;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentBilledEvery;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateScheduledInvoices extends Command
{
    protected $signature = 'invoices:generate-scheduled
                          {--days-ahead=7 : How many days ahead to generate invoices}
                          {--enrolment-id= : Generate for specific enrolment ID}
                          {--tenant-id= : Generate for specific tenant ID}
                          {--dry-run : Show what invoices would be generated without creating them}';

    protected $description = 'Generate scheduled invoices for child enrolments';

    public function handle(ChildEnrolmentInvoiceService $invoiceService): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $enrolmentId = $this->option('enrolment-id');
        $tenantId = $this->option('tenant-id');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE: No invoices will be created');
        }

        $this->info('Starting invoice generation...');

        // Include enrolments that may not be ACTIVE yet but need to be activated when invoiced
        $allowedStatuses = [
            ChildEnrolmentStatus::ACTIVE,
            ChildEnrolmentStatus::DRAFT,
            ChildEnrolmentStatus::PENDING,
            ChildEnrolmentStatus::INACTIVE
        ];

        $query = ChildEnrolment::whereIn('status', $allowedStatuses)
            ->with(['child.users', 'centre', 'product']);

        if ($enrolmentId) {
            $query->where('id', $enrolmentId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $enrolments = $query->get();

        // Filter enrolments that need invoicing using service logic
        $enrolmentsNeedingInvoices = $enrolments->filter(function ($enrolment) use ($daysAhead, $invoiceService) {
            return $invoiceService->shouldGenerateInvoices($enrolment, $daysAhead);
        });

        if ($enrolmentsNeedingInvoices->isEmpty()) {
            $this->info('No enrolments need invoicing at this time.');
            return 0;
        }

        $this->info("Found {$enrolmentsNeedingInvoices->count()} enrolment(s) that need invoicing...");

        if ($dryRun) {
            $this->info('📋 DRY RUN - Showing what would be generated:');

            // Show what would be generated without actually creating invoices
            $groupedEnrolments = $this->groupEnrolmentsByParentAndCentre($enrolmentsNeedingInvoices);
            $invoiceCount = 0;

            foreach ($groupedEnrolments as $group) {
                $invoiceCount++;
                $parent = $group['parent'];
                $centre = $group['centre'];
                $children = $group['enrolments']->map(function ($enrolment) {
                    return $enrolment->child->full_name;
                })->join(', ');

                $this->info("  - Would create Invoice #{$invoiceCount} for {$parent->name} at {$centre->name} (Children: {$children})");

                // Show billing details for each enrolment
                foreach ($group['enrolments'] as $enrolment) {
                    $nextBillingDate = $invoiceService->getNextBillingPeriodStart($enrolment);
                    $this->line("    • {$enrolment->child->full_name}: {$enrolment->product->name} (Next billing: {$nextBillingDate->format('M j, Y')})");
                }
            }

            $this->info("📊 Total invoices that would be generated: {$invoiceCount}");
            return 0;
        }

        try {
            $invoices = $invoiceService->generateInvoicesForEnrolments($enrolmentsNeedingInvoices);
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

    private function groupEnrolmentsByParentAndCentre($enrolments): array
    {
        $grouped = [];

        foreach ($enrolments as $enrolment) {
            // Get the parent/guardian user
            $parent = $enrolment->child->users()->first();
            if (!$parent) {
                continue; // Skip if no parent found
            }

            // Group by tenant_id + user_id + centre_id
            $groupKey = $enrolment->tenant_id . '_' . $parent->id . '_' . $enrolment->centre_id;

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'parent' => $parent,
                    'centre' => $enrolment->centre,
                    'tenant_id' => $enrolment->tenant_id,
                    'enrolments' => collect(),
                ];
            }

            $grouped[$groupKey]['enrolments']->push($enrolment);
        }

        return $grouped;
    }
}
