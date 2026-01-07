<?php

namespace App\Console\Commands;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Models\ChildEnrolment;
use App\Services\ChildEnrolmentInvoiceService;
use Illuminate\Console\Command;

class UpdateQuarterlyEnrolmentsNextBillDate extends Command
{
    protected $signature = 'enrolments:update-next-bill-date-quarterly
                          {--dry-run : Show what would be updated without actually updating}
                          {--tenant= : Process for specific tenant ID}
                          {--enrolment= : Process for specific enrolment ID}
                          {--chunk=500 : Number of records to process per batch}';

    protected $description = 'Update next_bill_date for quarterly billed enrolments';

    public function handle(ChildEnrolmentInvoiceService $invoiceService): int
    {
        $isDryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');
        $enrolmentId = $this->option('enrolment');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE: No records will be updated');
        }

        $this->info('Starting next_bill_date update for QUARTERLY billed enrolments...');

        $query = ChildEnrolment::query()
            ->where('billed_every', ChildEnrolmentBilledEvery::QUARTERLY)
            ->whereIn('status', [
                ChildEnrolmentStatus::ACTIVE,
                ChildEnrolmentStatus::PENDING,
                ChildEnrolmentStatus::DRAFT,
            ]);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($enrolmentId) {
            $query->where('id', $enrolmentId);
        }

        $totalRecords = $query->count();

        if ($totalRecords === 0) {
            $this->info('No QUARTERLY billed enrolments to process.');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalRecords} QUARTERLY billed enrolment(s) to process.");

        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        $query->chunk($chunkSize, function ($enrolments) use ($invoiceService, $isDryRun, &$updatedCount, &$skippedCount, &$errorCount, $bar) {
            foreach ($enrolments as $enrolment) {
                try {
                    $nextBillDate = $invoiceService->getNextBillingPeriodStart($enrolment);

                    $hasChanged = ($enrolment->next_bill_date?->toDateString() !== $nextBillDate?->toDateString());

                    if ($hasChanged) {
                        if (! $isDryRun) {
                            ChildEnrolment::withoutEvents(function () use ($enrolment, $nextBillDate) {
                                $enrolment->next_bill_date = $nextBillDate;
                                $enrolment->save();
                            });
                        }
                        $updatedCount++;

                        if ($this->option('verbose')) {
                            $this->newLine();
                            $this->line("  Updated Enrolment #{$enrolment->id}: ".($nextBillDate?->format('Y-m-d') ?? 'null'));
                        }
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    if ($this->option('verbose')) {
                        $this->newLine();
                        $this->error("  Error processing Enrolment #{$enrolment->id}: ".$e->getMessage());
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('📊 Summary:');
        $this->info("  Total processed: {$totalRecords}");
        $this->info("  Updated: {$updatedCount}");
        $this->info("  Skipped (no change): {$skippedCount}");

        if ($errorCount > 0) {
            $this->warn("  Errors: {$errorCount}");
        }

        if ($isDryRun) {
            $this->info('✅ DRY RUN completed - no changes were made.');
        } else {
            $this->info('✅ Update completed successfully.');
        }

        return Command::SUCCESS;
    }
}
