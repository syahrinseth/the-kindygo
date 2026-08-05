<?php

namespace App\Console\Commands;

use App\Services\Migration\MigrationLogger;
use App\Services\Migration\OrphanLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyQuotations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-quotations
                            {--dry-run : Run without making changes}
                            {--chunk=500 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}
                            {--skip-existing : Skip records already migrated or conflicting (faster re-runs)}
                            {--start-id=0 : Start from a specific legacy quotation ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy quotations (1_quotations → quotations) and quotation transactions (1_quotation_transactions → quotation_items)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $tenantId = (int) $this->option('tenant-id');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting legacy quotations migration...');

        $this->migrateQuotations($tenantId, $chunkSize, $dryRun);
        $this->migrateQuotationItems($chunkSize, $dryRun);
        $this->recalculateQuotationTotals($tenantId, $dryRun);

        $this->newLine();
        $this->info('Legacy quotations migration completed!');

        return Command::SUCCESS;
    }

    private function migrateQuotations(int $tenantId, int $chunkSize, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Migrating Quotations ---');

        $logger = new MigrationLogger('phase_3c_quotations', '1_quotations', 'quotations');
        $validUserIds = array_flip(DB::table('users')->pluck('id')->all());
        $validCentreIds = array_flip(DB::table('centres')->pluck('id')->all());
        $existingIds = array_flip(DB::table('quotations')->pluck('id')->all());
        $usedNumbers = array_flip(DB::table('quotations')->pluck('number')->all());
        $startId = (int) $this->option('start-id');
        $skipExisting = (bool) $this->option('skip-existing');

        $query = DB::connection('legacy')->table('1_quotations')->orderBy('id');
        if ($startId > 0) {
            $query->where('id', '>=', $startId);
        }

        $total = $query->count();
        $logger->setTotalSource($total);
        $this->info("Found {$total} legacy quotations to migrate.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($chunkSize, function ($legacyQuotations) use ($tenantId, $dryRun, $logger, $bar, $validUserIds, $validCentreIds, &$existingIds, &$usedNumbers, $skipExisting): void {
            $batch = [];

            foreach ($legacyQuotations as $legacy) {
                if (isset($existingIds[$legacy->id])) {
                    if (! $skipExisting) {
                        $this->logConflict('1_quotations', $legacy->id, 'Target quotation ID already exists');
                    }
                    $logger->incrementSkipped();
                    $bar->advance();

                    continue;
                }

                $userId = $legacy->parent_id ? (int) $legacy->parent_id : null;
                $centreId = $legacy->preschool_id ? (int) $legacy->preschool_id : null;
                if ($userId === null || ! isset($validUserIds[$userId])) {
                    $this->logConflict('1_quotations', $legacy->id, "user_id {$userId} not found", ['parent_id' => $legacy->parent_id]);
                    $logger->incrementSkipped();
                    $bar->advance();

                    continue;
                }
                if ($centreId === null || ! isset($validCentreIds[$centreId])) {
                    $this->logConflict('1_quotations', $legacy->id, "centre_id {$centreId} not found", ['preschool_id' => $legacy->preschool_id]);
                    $logger->incrementSkipped();
                    $bar->advance();

                    continue;
                }

                $date = $legacy->date ?? $legacy->created_at ?? now();
                $number = $this->uniqueNumber($legacy->quotation_no, (int) $legacy->id, $usedNumbers);
                $batch[] = [
                    'id' => $legacy->id,
                    'number' => $number,
                    'tenant_id' => $tenantId,
                    'centre_id' => $centreId,
                    'user_id' => $userId,
                    'child_id' => null,
                    'date' => $date,
                    'valid_until' => $date,
                    'status' => 'expired',
                    'converted_invoice_id' => null,
                    'total_items' => 0,
                    'total_amount' => 0,
                    'total' => 0,
                    'terms_conditions' => null,
                    'notes' => null,
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ];
                $existingIds[$legacy->id] = true;
                $logger->incrementMigrated();
                $bar->advance();
            }

            if (! $dryRun && $batch !== []) {
                DB::table('quotations')->insert($batch);
            }
        });

        $bar->finish();
        $logger->complete();
        $this->newLine();
    }

    private function migrateQuotationItems(int $chunkSize, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Migrating Quotation Items ---');

        $logger = new MigrationLogger('phase_3c_quotation_items', '1_quotation_transactions', 'quotation_items');
        $validQuotationIds = array_flip(DB::table('quotations')->pluck('id')->all());
        $validProductIds = array_flip(DB::table('products')->pluck('id')->all());
        $validChildIds = array_flip(DB::table('children')->pluck('id')->all());
        $existingIds = array_flip(DB::table('quotation_items')->pluck('id')->all());
        $startId = (int) $this->option('start-id');
        $skipExisting = (bool) $this->option('skip-existing');
        $enrolments = [];
        DB::table('child_enrolments')->select('id', 'child_id', 'product_id')->orderBy('id')->each(function (object $enrolment) use (&$enrolments): void {
            $enrolments[$enrolment->child_id.'|'.$enrolment->product_id] = $enrolment->id;
        });
        if ($dryRun) {
            foreach (DB::connection('legacy')->table('1_quotations')->pluck('id') as $quotationId) {
                $validQuotationIds[$quotationId] = true;
            }
        }
        $query = DB::connection('legacy')->table('1_quotation_transactions')->orderBy('id');
        if ($startId > 0) {
            $query->where('quotation_id', '>=', $startId);
        }
        $total = $query->count();
        $logger->setTotalSource($total);
        $this->info("Found {$total} legacy quotation transactions to migrate.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($chunkSize, function ($legacyItems) use ($dryRun, $logger, $bar, $validQuotationIds, $validProductIds, $validChildIds, &$existingIds, $enrolments, $skipExisting): void {
            $batch = [];
            foreach ($legacyItems as $legacy) {
                if (isset($existingIds[$legacy->id])) {
                    if (! $skipExisting) {
                        $this->logConflict('1_quotation_transactions', $legacy->id, 'Target quotation item ID already exists');
                    }
                    $logger->incrementSkipped();
                    $bar->advance();

                    continue;
                }

                $quotationId = $legacy->quotation_id ? (int) $legacy->quotation_id : null;
                if ($quotationId === null || ! isset($validQuotationIds[$quotationId])) {
                    $this->logConflict('1_quotation_transactions', $legacy->id, "quotation_id {$quotationId} not found", ['quotation_id' => $legacy->quotation_id]);
                    $logger->incrementSkipped();
                    $bar->advance();

                    continue;
                }

                $productId = $legacy->product_id ? (int) $legacy->product_id : null;
                $childId = $legacy->child_id ? (int) $legacy->child_id : null;
                $productId = $productId !== null && isset($validProductIds[$productId]) ? $productId : null;
                $childId = $childId !== null && isset($validChildIds[$childId]) ? $childId : null;
                $quantity = max(1, (int) ($legacy->quantity ?? 1));
                $price = (int) ($legacy->amount ?? 0);
                $discount = abs((int) ($legacy->discount_amount ?? 0));
                $totalAmount = ($price * $quantity) - ($discount * $quantity);
                $childEnrolmentId = $childId !== null && $productId !== null ? ($enrolments[$childId.'|'.$productId] ?? null) : null;

                $batch[] = [
                    'id' => $legacy->id,
                    'quotation_id' => $quotationId,
                    'product_id' => $productId,
                    'child_id' => $childId,
                    'child_enrolment_id' => $childEnrolmentId,
                    'name' => $legacy->label ?? 'Legacy Item',
                    'description' => $legacy->remarks,
                    'price' => $price,
                    'quantity' => $quantity,
                    'discount' => $discount,
                    'total' => $totalAmount,
                    'type' => 'product',
                    'paid_amount' => 0,
                    'balance_amount' => $totalAmount,
                    'paid' => false,
                    'effective_date' => $legacy->bill_date,
                    'period_start' => null,
                    'period_end' => null,
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ];
                $existingIds[$legacy->id] = true;
                $logger->incrementMigrated();
                $bar->advance();
            }

            if (! $dryRun && $batch !== []) {
                DB::table('quotation_items')->insert($batch);
            }
        });

        $bar->finish();
        $logger->complete();
        $this->newLine();
    }

    /** @param array<string, true> $usedNumbers */
    private function uniqueNumber(?string $legacyNumber, int $legacyId, array &$usedNumbers): string
    {
        $base = trim((string) $legacyNumber);
        $base = $base !== '' ? $base : "LEGACY-QUO-{$legacyId}";
        $candidate = $base;
        $suffix = 2;
        while (isset($usedNumbers[$candidate])) {
            $candidate = "{$base}-DUP{$suffix}";
            $suffix++;
        }
        $usedNumbers[$candidate] = true;

        return $candidate;
    }

    private function recalculateQuotationTotals(int $tenantId, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        DB::statement('UPDATE quotations SET total_items = COALESCE((SELECT COUNT(*) FROM quotation_items WHERE quotation_items.quotation_id = quotations.id), 0), total_amount = COALESCE((SELECT SUM(total) FROM quotation_items WHERE quotation_items.quotation_id = quotations.id), 0), total = COALESCE((SELECT SUM(total) FROM quotation_items WHERE quotation_items.quotation_id = quotations.id), 0) WHERE tenant_id = ?', [$tenantId]);
    }

    /** @param array<string, mixed>|null $data */
    private function logConflict(string $sourceTable, int $sourceId, string $reason, ?array $data = null): void
    {
        OrphanLogger::log($sourceTable, $sourceId, $reason, $data);
    }
}
