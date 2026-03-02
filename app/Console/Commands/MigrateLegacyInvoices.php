<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use App\Services\Migration\OrphanLogger;
use App\Services\Migration\StatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-invoices
                            {--dry-run : Run without making changes}
                            {--chunk=500 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}
                            {--skip-existing : Skip records already migrated (faster re-runs)}
                            {--start-id=0 : Start from a specific legacy ID (skips all records below this ID)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy invoices (1_invoices → invoices) and bill transactions (1_transactions type=bill → invoice_items)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $tenantId = (int) $this->option('tenant-id');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy invoices migration...');

        // Step 1: Migrate invoices
        $result = $this->migrateInvoices($tenantId, $chunkSize, $dryRun);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        // Step 2: Migrate invoice items (bill transactions)
        $result = $this->migrateInvoiceItems($chunkSize, $dryRun);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        // Step 3: Recalculate invoice totals from migrated items
        $this->recalculateInvoiceTotals($tenantId, $chunkSize, $dryRun);

        $this->newLine();
        $this->info('Legacy invoices migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Migrate legacy invoices to the invoices table.
     */
    private function migrateInvoices(int $tenantId, int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Invoices ---');

        $logger = new MigrationLogger('phase_3a_invoices', '1_invoices', 'invoices');
        $skipExisting = $this->option('skip-existing');

        // Use flipped arrays for O(1) lookup instead of in_array O(n)
        $existingIds = $skipExisting
            ? array_flip(DB::table('invoices')->pluck('id')->toArray())
            : [];

        // Pre-load valid user IDs and centre IDs for validation (small datasets)
        $validUserIds = array_flip(DB::table('users')->pluck('id')->toArray());
        $validCentreIds = array_flip(DB::table('centres')->pluck('id')->toArray());

        // Track used invoice numbers per centre to handle duplicates
        $usedNumbers = [];
        if (! $dryRun) {
            DB::table('invoices')
                ->where('tenant_id', $tenantId)
                ->select('number', 'centre_id')
                ->orderBy('id')
                ->chunk(2000, function ($existing) use (&$usedNumbers) {
                    foreach ($existing as $inv) {
                        $key = $inv->centre_id.'|'.$inv->number;
                        $usedNumbers[$key] = true;
                    }
                });
        }

        $totalCount = DB::connection('legacy')
            ->table('1_invoices')
            ->whereNull('deleted_at')
            ->count();

        $startId = (int) $this->option('start-id');

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy invoices to migrate.");

        if ($startId > 0) {
            $this->info("Starting from ID {$startId} (skipping earlier records).");
        }

        if ($skipExisting && count($existingIds) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingIds).' already-migrated invoices.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $query = DB::connection('legacy')
            ->table('1_invoices')
            ->whereNull('deleted_at')
            ->orderBy('id');

        if ($startId > 0) {
            $query->where('id', '>=', $startId);

            // Advance progress bar for skipped records
            $skippedByStartId = DB::connection('legacy')
                ->table('1_invoices')
                ->whereNull('deleted_at')
                ->where('id', '<', $startId)
                ->count();
            $bar->advance($skippedByStartId);
            $logger->incrementSkipped($skippedByStartId);
        }

        $query->chunk($chunkSize, function ($legacyInvoices) use ($tenantId, $dryRun, $logger, $bar, $validUserIds, $validCentreIds, $skipExisting, $existingIds, &$usedNumbers) {
            $batch = [];

            foreach ($legacyInvoices as $legacy) {
                try {
                    if ($skipExisting && isset($existingIds[$legacy->id])) {
                        $logger->incrementSkipped();
                        $bar->advance();

                        continue;
                    }

                    $row = $this->buildInvoiceRow($legacy, $tenantId, $validUserIds, $validCentreIds, $usedNumbers);
                    if ($row !== null) {
                        $batch[] = $row;
                        $logger->incrementMigrated();
                    }
                } catch (\Exception $e) {
                    $logger->logError("Invoice {$legacy->id} ({$legacy->invoice_no}): {$e->getMessage()}", $legacy->id);
                    $this->newLine();
                    $this->error("Error migrating invoice {$legacy->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            // Batch upsert for the entire chunk
            if (! $dryRun && ! empty($batch)) {
                DB::table('invoices')->upsert(
                    $batch,
                    ['id'],
                    ['number', 'tenant_id', 'centre_id', 'user_id', 'date', 'due_at', 'status', 'total_items', 'total_discounts', 'total_amount', 'total', 'updated_at']
                );
            }
        });

        $bar->finish();
        $logger->complete();
        $this->newLine();

        $log = $logger->getLog();
        $this->table(['Metric', 'Count'], [
            ['Source', $log->total_source],
            ['Migrated', $log->total_migrated],
            ['Skipped', $log->total_skipped],
            ['Errors', $log->total_errors],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Build an invoice row from a legacy record.
     *
     * @param  array<int, true>  $validUserIds
     * @param  array<int, true>  $validCentreIds
     * @param  array<string, bool>  $usedNumbers
     * @return array<string, mixed>|null
     */
    private function buildInvoiceRow(object $legacy, int $tenantId, array $validUserIds, array $validCentreIds, array &$usedNumbers): ?array
    {
        // Validate user_id
        $userId = $legacy->parent ? (int) $legacy->parent : null;
        if ($userId !== null && ! isset($validUserIds[$userId])) {
            OrphanLogger::log(
                '1_invoices',
                $legacy->id,
                "user_id {$userId} not found in users table",
                ['invoice_id' => $legacy->id, 'parent' => $userId]
            );
            $userId = null; // Set null but still migrate the invoice
        }

        // Validate centre_id
        $centreId = $legacy->preschool ? (int) $legacy->preschool : null;
        if ($centreId !== null && ! isset($validCentreIds[$centreId])) {
            OrphanLogger::log(
                '1_invoices',
                $legacy->id,
                "centre_id {$centreId} not found in centres table",
                ['invoice_id' => $legacy->id, 'preschool' => $centreId]
            );
            $centreId = null;
        }

        // Generate invoice number: replace spaces with hyphens
        $invoiceNumber = $this->generateInvoiceNumber($legacy, $centreId, $usedNumbers);

        // Map status
        $status = StatusMapper::invoiceStatus((int) ($legacy->payment_status ?? 1));

        // Parse due_date (date → datetime)
        $dueAt = $legacy->due_date ? $legacy->due_date.' 23:59:59' : null;

        // Fallback date: use invoice_date, or created_at, or updated_at, or now()
        $invoiceDate = $legacy->invoice_date ?? $legacy->created_at ?? $legacy->updated_at ?? now();

        return [
            'id' => $legacy->id,
            'number' => $invoiceNumber,
            'tenant_id' => $tenantId,
            'centre_id' => $centreId,
            'user_id' => $userId,
            'date' => $invoiceDate,
            'due_at' => $dueAt,
            'status' => $status->value,
            'total_items' => 0,       // Will be recalculated
            'total_discounts' => 0,   // Will be recalculated
            'total_amount' => 0,      // Will be recalculated
            'total' => $legacy->price ?? 0, // Preserve legacy total, recalculated later
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at ?? now(),
        ];
    }

    /**
     * Generate a unique invoice number from legacy invoice_no.
     * Replaces spaces with hyphens and handles duplicates/nulls.
     *
     * @param  array<string, bool>  $usedNumbers
     */
    private function generateInvoiceNumber(object $legacy, ?int $centreId, array &$usedNumbers): string
    {
        if (empty($legacy->invoice_no)) {
            // Generate fallback for null invoice_no
            $baseNumber = 'LEGACY-'.$legacy->id;
        } else {
            // Replace spaces with hyphens
            $baseNumber = str_replace(' ', '-', trim($legacy->invoice_no));
        }

        // Check for uniqueness within tenant+centre
        $key = $centreId.'|'.$baseNumber;
        if (! isset($usedNumbers[$key])) {
            $usedNumbers[$key] = true;

            return $baseNumber;
        }

        // Handle duplicates by appending suffix
        $suffix = 2;
        do {
            $candidateNumber = $baseNumber.'-DUP'.$suffix;
            $candidateKey = $centreId.'|'.$candidateNumber;
            $suffix++;
        } while (isset($usedNumbers[$candidateKey]));

        $usedNumbers[$candidateKey] = true;

        return $candidateNumber;
    }

    /**
     * Migrate legacy bill transactions to invoice_items table.
     */
    private function migrateInvoiceItems(int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Migrating Invoice Items (bill transactions) ---');

        $logger = new MigrationLogger('phase_3a_items', '1_transactions', 'invoice_items');
        $skipExisting = $this->option('skip-existing');

        // Use flipped arrays for O(1) lookup
        $existingIds = $skipExisting
            ? array_flip(DB::table('invoice_items')->pluck('id')->toArray())
            : [];

        // Pre-load valid foreign keys for validation using flipped arrays
        // DB::table() doesn't apply Eloquent soft-delete scoping, so children includes soft-deleted
        $validInvoiceIds = array_flip(DB::table('invoices')->pluck('id')->toArray());
        $validProductIds = array_flip(DB::table('products')->pluck('id')->toArray());
        $validChildIds = array_flip(DB::table('children')->pluck('id')->toArray());

        // Pre-load child enrolments for lookup: key = "child_id|product_id" → enrolment_id
        $enrolmentLookup = [];
        DB::table('child_enrolments')
            ->select('id', 'child_id', 'product_id')
            ->orderBy('id')
            ->chunk(1000, function ($enrolments) use (&$enrolmentLookup) {
                foreach ($enrolments as $enrolment) {
                    $key = $enrolment->child_id.'|'.$enrolment->product_id;
                    $enrolmentLookup[$key] = $enrolment->id;
                }
            });

        $totalCount = DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'bill')
            ->whereNull('deleted_at')
            ->count();

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy bill transactions to migrate as invoice items.");

        if ($skipExisting && count($existingIds) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingIds).' already-migrated items.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        // Load invoice period dates per-chunk to avoid memory exhaustion
        DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'bill')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($chunkSize, function ($legacyItems) use ($dryRun, $logger, $bar, $validInvoiceIds, $validProductIds, $validChildIds, $enrolmentLookup, $skipExisting, $existingIds) {
                // Batch-load invoice period dates for this chunk
                $invoiceIdsInChunk = $legacyItems->pluck('invoice_id')->filter()->unique()->toArray();
                $invoicePeriods = [];
                if (! empty($invoiceIdsInChunk)) {
                    $periods = DB::connection('legacy')
                        ->table('1_invoices')
                        ->whereIn('id', $invoiceIdsInChunk)
                        ->select('id', 'start_date', 'end_date')
                        ->get();
                    foreach ($periods as $inv) {
                        $invoicePeriods[$inv->id] = [
                            'start_date' => $inv->start_date,
                            'end_date' => $inv->end_date,
                        ];
                    }
                }

                $batch = [];

                foreach ($legacyItems as $legacy) {
                    try {
                        if ($skipExisting && isset($existingIds[$legacy->id])) {
                            $logger->incrementSkipped();
                            $bar->advance();

                            continue;
                        }

                        $row = $this->buildInvoiceItemRow($legacy, $validInvoiceIds, $validProductIds, $validChildIds, $enrolmentLookup, $invoicePeriods);
                        if ($row !== null) {
                            $batch[] = $row;
                            $logger->incrementMigrated();
                        }
                    } catch (\Exception $e) {
                        $logger->logError("Transaction {$legacy->id} ({$legacy->label}): {$e->getMessage()}", $legacy->id);
                        $this->newLine();
                        $this->error("Error migrating transaction {$legacy->id}: {$e->getMessage()}");
                    }

                    $bar->advance();
                }

                // Batch upsert for the entire chunk
                if (! $dryRun && ! empty($batch)) {
                    DB::table('invoice_items')->upsert(
                        $batch,
                        ['id'],
                        ['invoice_id', 'product_id', 'child_id', 'child_enrolment_id', 'name', 'description', 'price', 'quantity', 'discount', 'total', 'period_start', 'period_end', 'type', 'paid_amount', 'balance_amount', 'paid', 'effective_date', 'updated_at']
                    );
                }
            });

        $bar->finish();
        $logger->complete();
        $this->newLine();

        $log = $logger->getLog();
        $this->table(['Metric', 'Count'], [
            ['Source', $log->total_source],
            ['Migrated', $log->total_migrated],
            ['Skipped', $log->total_skipped],
            ['Errors', $log->total_errors],
        ]);

        return Command::SUCCESS;
    }

    /**
     * Build an invoice item row from a legacy bill transaction.
     * Returns null if the item should be skipped (orphaned invoice).
     *
     * @param  array<int, true>  $validInvoiceIds  Flipped array for O(1) lookup
     * @param  array<int, true>  $validProductIds  Flipped array for O(1) lookup
     * @param  array<int, true>  $validChildIds  Flipped array for O(1) lookup
     * @param  array<string, int>  $enrolmentLookup
     * @param  array<int, array{start_date: ?string, end_date: ?string}>  $invoicePeriods
     * @return array<string, mixed>|null
     */
    private function buildInvoiceItemRow(object $legacy, array $validInvoiceIds, array $validProductIds, array $validChildIds, array $enrolmentLookup, array $invoicePeriods): ?array
    {
        // Validate invoice_id — required FK
        $invoiceId = $legacy->invoice_id ? (int) $legacy->invoice_id : null;
        if ($invoiceId === null || ! isset($validInvoiceIds[$invoiceId])) {
            OrphanLogger::log(
                '1_transactions',
                $legacy->id,
                "invoice_id {$invoiceId} not found in invoices table",
                ['transaction_id' => $legacy->id, 'invoice_id' => $invoiceId, 'type' => 'bill']
            );

            return null; // Skip — can't create item without valid invoice
        }

        // Validate product_id — nullable FK
        $productId = $legacy->product_id ? (int) $legacy->product_id : null;
        if ($productId !== null && ! isset($validProductIds[$productId])) {
            OrphanLogger::log(
                '1_transactions',
                $legacy->id,
                "product_id {$productId} not found in products table",
                ['transaction_id' => $legacy->id, 'product_id' => $productId]
            );
            $productId = null; // Set null but still migrate
        }

        // Validate child_id — nullable FK
        $childId = $legacy->child_id ? (int) $legacy->child_id : null;
        if ($childId !== null && ! isset($validChildIds[$childId])) {
            OrphanLogger::log(
                '1_transactions',
                $legacy->id,
                "child_id {$childId} not found in children table",
                ['transaction_id' => $legacy->id, 'child_id' => $childId]
            );
            $childId = null;
        }

        // Lookup child_enrolment_id
        $childEnrolmentId = null;
        if ($childId !== null && $productId !== null) {
            $key = $childId.'|'.$productId;
            $childEnrolmentId = $enrolmentLookup[$key] ?? null;
        }

        // Calculate amounts
        $price = (int) ($legacy->amount ?? 0);
        $quantity = (int) ($legacy->quantity ?? 1);
        $discount = (int) ($legacy->discount_amount ?? 0);
        $total = ($price * $quantity) - ($discount * $quantity);

        // Determine item type
        $type = ($legacy->is_discount || $price < 0) ? 'invoice_discount' : 'product';

        // Get period dates from pre-loaded invoice periods
        $periodStart = $invoicePeriods[$invoiceId]['start_date'] ?? null;
        $periodEnd = $invoicePeriods[$invoiceId]['end_date'] ?? null;

        // Parse effective_date from bill_date
        $effectiveDate = null;
        if (! empty($legacy->bill_date)) {
            try {
                $effectiveDate = \Carbon\Carbon::parse($legacy->bill_date)->toDateString();
            } catch (\Exception $e) {
                $effectiveDate = null;
            }
        }

        return [
            'id' => $legacy->id,
            'invoice_id' => $invoiceId,
            'product_id' => $productId,
            'child_id' => $childId,
            'child_enrolment_id' => $childEnrolmentId,
            'name' => $legacy->label ?? 'Legacy Item',
            'description' => $legacy->remarks,
            'price' => $price,
            'quantity' => $quantity,
            'discount' => abs($discount),
            'total' => $total,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'type' => $type,
            'paid_amount' => 0,       // Will be calculated after payments migration
            'balance_amount' => $total, // Will be recalculated
            'paid' => false,
            'effective_date' => $effectiveDate,
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at ?? now(),
        ];
    }

    /**
     * Recalculate invoice totals based on migrated invoice items.
     * Uses aggregate SQL to avoid loading all items into memory.
     */
    private function recalculateInvoiceTotals(int $tenantId, int $chunkSize, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Recalculating Invoice Totals ---');

        $totalInvoices = DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->count();

        $this->info("Recalculating totals for {$totalInvoices} invoices...");

        if ($dryRun) {
            $this->info('DRY RUN: Skipping recalculation.');

            return;
        }

        // Use a single UPDATE with subquery for efficient bulk recalculation
        // This avoids loading items into PHP memory entirely
        $updated = DB::statement('
            UPDATE invoices
            SET
                total_items = COALESCE((
                    SELECT COUNT(*) FROM invoice_items WHERE invoice_items.invoice_id = invoices.id
                ), 0),
                total_amount = COALESCE((
                    SELECT SUM(price * quantity) FROM invoice_items WHERE invoice_items.invoice_id = invoices.id
                ), 0),
                total_discounts = COALESCE((
                    SELECT SUM(discount * quantity) FROM invoice_items WHERE invoice_items.invoice_id = invoices.id
                ), 0),
                total = COALESCE((
                    SELECT SUM(total) FROM invoice_items WHERE invoice_items.invoice_id = invoices.id
                ), 0)
            WHERE tenant_id = ?
        ', [$tenantId]);

        $this->info("Recalculated totals for {$totalInvoices} invoices.");
    }
}
