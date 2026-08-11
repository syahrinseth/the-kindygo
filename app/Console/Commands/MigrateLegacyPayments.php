<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Migration\MigrationLogger;
use App\Services\Migration\OrphanLogger;
use App\Services\Migration\StatusMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-payments
                            {--dry-run : Run without making changes}
                            {--chunk=2000 : Number of records to process at once}
                            {--tenant-id=1 : Target tenant ID}
                            {--skip-existing : Skip records already migrated (faster re-runs)}
                            {--start-id=0 : Start from a specific legacy ID (skips all records below this ID)}
                            {--end-id= : Stop after this legacy transaction ID}
                            {--skip-payments : Skip payment records}
                            {--skip-pivots : Skip invoice-payment pivot records}
                            {--skip-status-update : Skip invoice status updates until the final batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy payment transactions (1_transactions → payments + invoice_payment pivot)';

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

        $this->info('Starting legacy payments migration...');

        if (! $this->option('skip-payments')) {
            $result = $this->migratePayments($tenantId, $chunkSize, $dryRun);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        if (! $this->option('skip-pivots')) {
            $result = $this->migrateInvoicePaymentPivot($chunkSize, $dryRun);
            if ($result !== Command::SUCCESS) {
                return $result;
            }
        }

        if (! $this->option('skip-status-update')) {
            $this->updateInvoiceStatusesFromPayments($tenantId, $dryRun);
        }

        $this->newLine();
        $this->info('Legacy payments migration completed!');

        return Command::SUCCESS;
    }

    /**
     * Step 1: Migrate legacy payment transactions to the payments table.
     */
    private function migratePayments(int $tenantId, int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Step 1: Migrating Payments ---');

        $logger = new MigrationLogger('phase_3b_payments', '1_transactions', 'payments');
        $skipExisting = $this->option('skip-existing');
        $startId = (int) $this->option('start-id');
        $endId = $this->option('end-id') !== null ? (int) $this->option('end-id') : null;

        $this->reconcilePreviouslyMigratedDeposits($dryRun);

        // Use flipped arrays for O(1) lookup
        $existingIds = $skipExisting
            ? array_flip(DB::table('payments')->pluck('id')->toArray())
            : [];

        // Pre-load valid user IDs for validation
        $validUserIds = array_flip(DB::table('users')->pluck('id')->toArray());

        $totalCount = DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'payment')
            ->whereNull('deleted_at')
            ->count();

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy payment transactions to migrate.");

        if ($startId > 0) {
            $this->info("Starting from ID {$startId} (skipping earlier records).");
        }

        if ($skipExisting && count($existingIds) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingIds).' already-migrated payments.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $query = DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'payment')
            ->whereNull('deleted_at')
            ->orderBy('id');

        if ($startId > 0) {
            $query->where('id', '>=', $startId);

            $skippedByStartId = DB::connection('legacy')
                ->table('1_transactions')
                ->where('type', 'payment')
                ->whereNull('deleted_at')
                ->where('id', '<', $startId)
                ->count();
            $bar->advance($skippedByStartId);
            $logger->incrementSkipped($skippedByStartId);
        }

        if ($endId !== null) {
            $query->where('id', '<=', $endId);
        }

        $query->chunk($chunkSize, function ($legacyTransactions) use ($tenantId, $dryRun, $logger, $bar, $validUserIds, $skipExisting, $existingIds) {
            $batch = [];

            foreach ($legacyTransactions as $legacy) {
                try {
                    if ($skipExisting && isset($existingIds[$legacy->id])) {
                        $logger->incrementSkipped();
                        $bar->advance();

                        continue;
                    }

                    $row = $this->buildPaymentRow($legacy, $tenantId, $validUserIds);
                    if ($row !== null) {
                        $batch[] = $row;
                        $logger->incrementMigrated();
                    } else {
                        $logger->incrementSkipped();
                    }
                } catch (\Exception $e) {
                    $logger->logError("Transaction {$legacy->id} ({$legacy->type}): {$e->getMessage()}", $legacy->id);
                    $this->newLine();
                    $this->error("Error migrating transaction {$legacy->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            // Batch upsert for the entire chunk
            if (! $dryRun && ! empty($batch)) {
                DB::table('payments')->upsert(
                    $batch,
                    ['id'],
                    ['tenant_id', 'user_id', 'gateway', 'reference_no', 'gateway_payment_id', 'gateway_payment_data', 'status', 'amount', 'description', 'meta', 'paid_at', 'updated_at']
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
     * Remove payments created by earlier migrations for legacy deposit transactions.
     *
     * Deposits are invoice items, not payments. Limit reconciliation to rows with the
     * legacy deposit metadata so unrelated application payments cannot be removed.
     */
    private function reconcilePreviouslyMigratedDeposits(bool $dryRun): void
    {
        $this->info('Reconciling previously migrated deposit transactions...');

        $reconciled = 0;
        DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'deposit')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($deposits) use ($dryRun, &$reconciled) {
                $payments = Payment::withoutGlobalScopes()
                    ->whereIn('id', $deposits->pluck('id'))
                    ->get()
                    ->filter(fn (Payment $payment): bool => data_get($payment->meta, 'legacy_type') === 'deposit');

                $reconciled += $payments->count();

                if ($dryRun) {
                    return;
                }

                $payments->each(function (Payment $payment): void {
                    $payment->clearMediaCollection('payment_proof');
                    $payment->delete();
                });
            });

        $this->info($dryRun
            ? "DRY RUN: {$reconciled} incorrectly migrated deposit payment(s) would be removed."
            : "Reconciled {$reconciled} incorrectly migrated deposit payment(s).");
    }

    /**
     * Build a payment row from a legacy transaction.
     *
     * @param  array<int, true>  $validUserIds  Flipped array for O(1) lookup
     * @return array<string, mixed>|null
     */
    private function buildPaymentRow(object $legacy, int $tenantId, array $validUserIds): ?array
    {
        // Validate user_id (parent_id) — required FK
        $userId = (int) $legacy->parent_id;
        if (! isset($validUserIds[$userId])) {
            OrphanLogger::log(
                '1_transactions',
                $legacy->id,
                "user_id (parent_id) {$userId} not found in users table",
                ['transaction_id' => $legacy->id, 'parent_id' => $userId, 'type' => $legacy->type]
            );

            return null; // Skip — user_id is a required FK on payments
        }

        $amount = (int) ($legacy->paid_amount ?? 0);

        // Determine gateway: check for billplz first, then map payment_method, default to BANK_TRANSFER
        $gateway = $this->resolveGateway($legacy);

        // Determine status
        $status = StatusMapper::paymentStatus((int) ($legacy->paid_status ?? 0));

        // Build gateway_payment_data JSON (billplz fields)
        $gatewayPaymentData = null;
        $gatewayPaymentId = null;
        if (! empty($legacy->billplz_bill_id)) {
            $gatewayPaymentId = (string) $legacy->billplz_bill_id;
            $gatewayPaymentData = json_encode(array_filter([
                'billplz_bill_id' => $legacy->billplz_bill_id,
                'billplz_collection_id' => $legacy->billplz_collection_id,
            ]));
        }

        // Build meta JSON
        $meta = array_filter([
            'legacy_label' => $legacy->label,
            'remark' => $legacy->remarks,
            'payment_by' => $legacy->payment_by,
            'prev_invoice_id' => $legacy->prev_invoice_id,
            'preschool_id' => $legacy->preschool_id,
            'child_id' => $legacy->child_id,
            'gateway_collection_id' => $legacy->billplz_collection_id,
            'legacy_payment_method' => $legacy->payment_method,
            'legacy' => [
                'transaction_type' => $legacy->type,
                'payment_slip_path' => $legacy->payment_slip,
            ],
        ], fn ($v) => $v !== null && $v !== '' && $v !== 0);

        return [
            'id' => $legacy->id,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'gateway' => $gateway->value,
            'reference_no' => $legacy->reference_id,
            'gateway_payment_id' => $gatewayPaymentId,
            'gateway_payment_data' => $gatewayPaymentData,
            'status' => $status->value,
            'amount' => $amount,
            'description' => $legacy->label,
            'meta' => ! empty($meta) ? json_encode($meta) : null,
            'paid_at' => $this->sanitiseDatetime($legacy->paid_at),
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at ?? now(),
        ];
    }

    /**
     * Sanitise a datetime value from legacy data.
     * Handles malformed dates like '0020-12-30' (should be '2020-12-30') and '1970-01-01' (epoch/invalid).
     */
    private function sanitiseDatetime(?string $datetime): ?string
    {
        if ($datetime === null) {
            return null;
        }

        try {
            $parsed = \Carbon\Carbon::parse($datetime);

            // Fix 2-digit year dates like 0020 → 2020
            if ($parsed->year < 100) {
                $parsed->year += 2000;
            }

            // Reject dates before 2000 (e.g., Unix epoch 1970-01-01)
            if ($parsed->year < 2000) {
                return null;
            }

            // Reject dates too far in the future
            if ($parsed->year > 2030) {
                return null;
            }

            return $parsed->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolve the gateway for a legacy transaction.
     * Priority: billplz_bill_id → payment_method mapping → default BANK_TRANSFER.
     */
    private function resolveGateway(object $legacy): \App\Enums\Gateway
    {
        // If billplz_bill_id is set, it's a Billplz payment
        if (! empty($legacy->billplz_bill_id)) {
            return \App\Enums\Gateway::BILLPLZ;
        }

        // If payment_method is set and valid, use the mapper
        if ($legacy->payment_method !== null && $legacy->payment_method > 0) {
            return StatusMapper::paymentGateway((int) $legacy->payment_method);
        }

        // Default for NULL, 0, or unknown payment_method
        return \App\Enums\Gateway::BANK_TRANSFER;
    }

    /**
     * Step 2: Create invoice_payment pivot entries linking payments to invoices.
     */
    private function migrateInvoicePaymentPivot(int $chunkSize, bool $dryRun): int
    {
        $this->newLine();
        $this->info('--- Step 2: Migrating Invoice-Payment Pivot ---');

        $logger = new MigrationLogger('phase_3b_pivot', '1_transactions', 'invoice_payment');
        $skipExisting = $this->option('skip-existing');
        $startId = (int) $this->option('start-id');
        $endId = $this->option('end-id') !== null ? (int) $this->option('end-id') : null;

        // Pre-load valid payment IDs and invoice IDs for FK validation
        $validPaymentIds = array_flip(DB::table('payments')->pluck('id')->toArray());
        $validInvoiceIds = array_flip(DB::table('invoices')->pluck('id')->toArray());

        // Pre-load existing pivot entries for skip-existing
        $existingPivots = [];
        if ($skipExisting) {
            DB::table('invoice_payment')
                ->select('payment_id', 'invoice_id')
                ->orderBy('payment_id')
                ->chunk(5000, function ($pivots) use (&$existingPivots) {
                    foreach ($pivots as $pivot) {
                        $existingPivots[$pivot->payment_id.'|'.$pivot->invoice_id] = true;
                    }
                });
        }

        $totalCount = DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'payment')
            ->whereNull('deleted_at')
            ->whereNotNull('invoice_id')
            ->count();

        $logger->setTotalSource($totalCount);
        $this->info("Found {$totalCount} legacy transactions with invoice links.");

        if ($skipExisting && count($existingPivots) > 0) {
            $this->info('Skip-existing mode: will skip '.count($existingPivots).' already-migrated pivot entries.');
        }

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $query = DB::connection('legacy')
            ->table('1_transactions')
            ->where('type', 'payment')
            ->whereNull('deleted_at')
            ->whereNotNull('invoice_id')
            ->orderBy('id');

        if ($startId > 0) {
            $query->where('id', '>=', $startId);

            $skippedByStartId = DB::connection('legacy')
                ->table('1_transactions')
                ->where('type', 'payment')
                ->whereNull('deleted_at')
                ->whereNotNull('invoice_id')
                ->where('id', '<', $startId)
                ->count();
            $bar->advance($skippedByStartId);
            $logger->incrementSkipped($skippedByStartId);
        }

        if ($endId !== null) {
            $query->where('id', '<=', $endId);
        }

        $query->chunk($chunkSize, function ($legacyTransactions) use ($dryRun, $logger, $bar, $validPaymentIds, $validInvoiceIds, $skipExisting, $existingPivots) {
            $batch = [];

            foreach ($legacyTransactions as $legacy) {
                try {
                    $paymentId = (int) $legacy->id;
                    $invoiceId = (int) $legacy->invoice_id;

                    // Check skip-existing
                    if ($skipExisting && isset($existingPivots[$paymentId.'|'.$invoiceId])) {
                        $logger->incrementSkipped();
                        $bar->advance();

                        continue;
                    }

                    // Validate payment exists in payments table
                    if (! isset($validPaymentIds[$paymentId])) {
                        // This payment was skipped in Step 1 (orphan user), skip pivot too
                        $logger->incrementSkipped();
                        $bar->advance();

                        continue;
                    }

                    // Validate invoice exists
                    if (! isset($validInvoiceIds[$invoiceId])) {
                        OrphanLogger::log(
                            '1_transactions',
                            $legacy->id,
                            "invoice_id {$invoiceId} not found in invoices table (pivot)",
                            ['transaction_id' => $legacy->id, 'invoice_id' => $invoiceId, 'type' => $legacy->type]
                        );
                        $logger->incrementSkipped();
                        $bar->advance();

                        continue;
                    }

                    $amount = (int) ($legacy->paid_amount ?? 0);

                    $batch[] = [
                        'payment_id' => $paymentId,
                        'invoice_id' => $invoiceId,
                        'amount' => $amount,
                        'created_at' => $legacy->created_at,
                        'updated_at' => $legacy->updated_at ?? now(),
                    ];

                    $logger->incrementMigrated();
                } catch (\Exception $e) {
                    $logger->logError("Pivot for transaction {$legacy->id}: {$e->getMessage()}", $legacy->id);
                    $this->newLine();
                    $this->error("Error creating pivot for transaction {$legacy->id}: {$e->getMessage()}");
                }

                $bar->advance();
            }

            // Batch upsert for the pivot (composite PK: payment_id + invoice_id)
            if (! $dryRun && ! empty($batch)) {
                DB::table('invoice_payment')->upsert(
                    $batch,
                    ['payment_id', 'invoice_id'],
                    ['amount', 'updated_at']
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
     * Step 3: Update invoice statuses based on total payments received.
     * Recalculates invoice_items paid_amount/balance_amount and updates invoice status.
     */
    private function updateInvoiceStatusesFromPayments(int $tenantId, bool $dryRun): void
    {
        $this->newLine();
        $this->info('--- Step 3: Updating Invoice Statuses from Payments ---');

        if ($dryRun) {
            $this->info('DRY RUN: Skipping invoice status updates.');

            return;
        }

        // Calculate total paid per invoice from the pivot table
        // Then update invoice status based on comparison with total
        $this->info('Calculating total payments per invoice and updating statuses...');

        // Update invoices where total payments >= total → PAID
        $paidCount = DB::update('
            UPDATE invoices
            SET status = ?
            WHERE tenant_id = ?
            AND status NOT IN (?, ?)
            AND total > 0
            AND (
                SELECT COALESCE(SUM(ip.amount), 0)
                FROM invoice_payment ip
                WHERE ip.invoice_id = invoices.id
            ) >= invoices.total
        ', [
            \App\Enums\InvoiceStatus::PAID->value,
            $tenantId,
            \App\Enums\InvoiceStatus::CANCELLED->value,
            \App\Enums\InvoiceStatus::REFUNDED->value,
        ]);

        $this->info("Updated {$paidCount} invoices to PAID status.");

        // Update invoices where total payments > 0 but < total → PARTIALLY_PAID
        $partialCount = DB::update('
            UPDATE invoices
            SET status = ?
            WHERE tenant_id = ?
            AND status NOT IN (?, ?, ?)
            AND total > 0
            AND (
                SELECT COALESCE(SUM(ip.amount), 0)
                FROM invoice_payment ip
                WHERE ip.invoice_id = invoices.id
            ) > 0
            AND (
                SELECT COALESCE(SUM(ip.amount), 0)
                FROM invoice_payment ip
                WHERE ip.invoice_id = invoices.id
            ) < invoices.total
        ', [
            \App\Enums\InvoiceStatus::PARTIALLY_PAID->value,
            $tenantId,
            \App\Enums\InvoiceStatus::CANCELLED->value,
            \App\Enums\InvoiceStatus::REFUNDED->value,
            \App\Enums\InvoiceStatus::PAID->value,
        ]);

        $this->info("Updated {$partialCount} invoices to PARTIALLY_PAID status.");

        // Summary
        $totalPayments = DB::table('payments')->where('tenant_id', $tenantId)->count();
        $totalPivots = DB::table('invoice_payment')
            ->join('payments', 'invoice_payment.payment_id', '=', 'payments.id')
            ->where('payments.tenant_id', $tenantId)
            ->count();

        $this->newLine();
        $this->table(['Summary', 'Count'], [
            ['Total payments', $totalPayments],
            ['Total invoice-payment links', $totalPivots],
            ['Invoices set to PAID', $paidCount],
            ['Invoices set to PARTIALLY_PAID', $partialCount],
        ]);
    }
}
