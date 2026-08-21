<?php

namespace App\Console\Commands;

use App\Enums\MalaysianState;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MigrateLegacyValidate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:legacy-validate
                            {--tenant-id=1 : Target tenant ID to validate}
                            {--fix : Attempt to fix issues found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate legacy migration data integrity — record counts, FK integrity, financial totals, orphan checks';

    /**
     * Track overall validation status.
     *
     * @var array<string, array{passed: int, failed: int, warnings: int, details: array<string>}>
     */
    private array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant-id');

        $this->info('========================================');
        $this->info('  Legacy Migration Validation Report');
        $this->info('========================================');
        $this->newLine();

        // 1. Record count validation
        $this->validateRecordCounts($tenantId);

        // 2. Foreign key integrity
        $this->validateForeignKeyIntegrity($tenantId);

        // 3. Financial data integrity
        $this->validateFinancialIntegrity($tenantId);

        // 4. Orphan records
        $this->validateOrphanRecords($tenantId);

        // 5. Enum/status consistency
        $this->validateEnumConsistency($tenantId);

        // 6. Media attachment summary
        $this->validateMediaAttachments();

        // 7. Migration logs summary
        $this->showMigrationLogsSummary();

        // Final summary
        $this->printFinalSummary();

        $totalFailed = collect($this->results)->sum('failed');

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Validate record counts between legacy and new database.
     */
    private function validateRecordCounts(int $tenantId): void
    {
        $this->info('--- 1. Record Count Validation ---');

        $checks = [
            [
                'label' => 'Centres',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_preschool` WHERE deleted_at IS NULL',
                'current' => "SELECT COUNT(*) as cnt FROM centres WHERE tenant_id = {$tenantId}",
            ],
            [
                'label' => 'Users',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_users` WHERE deleted_at IS NULL',
                'current' => "SELECT COUNT(*) as cnt FROM users WHERE id IN (SELECT user_id FROM tenant_user WHERE tenant_id = {$tenantId})",
            ],
            [
                'label' => 'Children (incl. soft-deleted)',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_child`',
                'current' => "SELECT COUNT(*) as cnt FROM children WHERE id IN (SELECT child_id FROM tenant_child WHERE tenant_id = {$tenantId})",
            ],
            [
                'label' => 'Children (active only)',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_child` WHERE deleted_at IS NULL',
                'current' => "SELECT COUNT(*) as cnt FROM children WHERE deleted_at IS NULL AND id IN (SELECT child_id FROM tenant_child WHERE tenant_id = {$tenantId})",
            ],
            [
                'label' => 'Products',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_product` WHERE deleted_at IS NULL',
                'current' => "SELECT COUNT(*) as cnt FROM products WHERE tenant_id = {$tenantId}",
            ],
            [
                'label' => 'Invoices',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_invoices` WHERE deleted_at IS NULL',
                'current' => "SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = {$tenantId}",
            ],
            [
                'label' => 'Invoice Items (bill+deposit)',
                'legacy' => "SELECT COUNT(*) as cnt FROM `1_transactions` WHERE type IN ('bill', 'deposit') AND deleted_at IS NULL",
                'current' => "SELECT COUNT(*) as cnt FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE tenant_id = {$tenantId})",
            ],
            [
                'label' => 'Payments',
                'legacy' => "SELECT COUNT(*) as cnt FROM `1_transactions` WHERE type = 'payment' AND deleted_at IS NULL",
                'current' => "SELECT COUNT(*) as cnt FROM payments WHERE tenant_id = {$tenantId}",
            ],
            [
                'label' => 'Quotations',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_quotations`',
                'current' => "SELECT COUNT(*) as cnt FROM quotations WHERE tenant_id = {$tenantId}",
            ],
            [
                'label' => 'Quotation Items',
                'legacy' => 'SELECT COUNT(*) as cnt FROM `1_quotation_transactions`',
                'current' => "SELECT COUNT(*) as cnt FROM quotation_items WHERE quotation_id IN (SELECT id FROM quotations WHERE tenant_id = {$tenantId})",
            ],
        ];

        $rows = [];
        foreach ($checks as $check) {
            $legacyCount = DB::connection('legacy')->selectOne($check['legacy'])->cnt;
            $currentCount = DB::selectOne($check['current'])->cnt;
            $diff = $currentCount - $legacyCount;
            $status = $diff === 0 ? 'PASS' : ($diff < 0 ? 'WARN' : 'PASS');

            $rows[] = [
                $check['label'],
                number_format($legacyCount),
                number_format($currentCount),
                $diff >= 0 ? "+{$diff}" : (string) $diff,
                $status,
            ];

            $this->trackResult('Record Counts', $status === 'PASS', $status === 'WARN',
                $status !== 'PASS' ? "{$check['label']}: legacy={$legacyCount}, current={$currentCount}" : null);
        }

        $this->table(['Entity', 'Legacy', 'Current', 'Diff', 'Status'], $rows);
        $this->newLine();
    }

    /**
     * Validate foreign key integrity — ensure no dangling references.
     */
    private function validateForeignKeyIntegrity(int $tenantId): void
    {
        $this->info('--- 2. Foreign Key Integrity ---');

        $checks = [
            [
                'label' => 'Invoices → Users',
                'query' => "SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = {$tenantId} AND user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)",
            ],
            [
                'label' => 'Invoices → Centres',
                'query' => "SELECT COUNT(*) as cnt FROM invoices WHERE tenant_id = {$tenantId} AND centre_id IS NOT NULL AND centre_id NOT IN (SELECT id FROM centres)",
            ],
            [
                'label' => 'Invoice Items → Invoices',
                'query' => "SELECT COUNT(*) as cnt FROM invoice_items WHERE invoice_id NOT IN (SELECT id FROM invoices WHERE tenant_id = {$tenantId})",
            ],
            [
                'label' => 'Invoice Items → Products',
                'query' => "SELECT COUNT(*) as cnt FROM invoice_items ii INNER JOIN invoices i ON ii.invoice_id = i.id WHERE i.tenant_id = {$tenantId} AND ii.product_id IS NOT NULL AND ii.product_id NOT IN (SELECT id FROM products)",
            ],
            [
                'label' => 'Invoice Items → Children',
                'query' => "SELECT COUNT(*) as cnt FROM invoice_items ii INNER JOIN invoices i ON ii.invoice_id = i.id WHERE i.tenant_id = {$tenantId} AND ii.child_id IS NOT NULL AND ii.child_id NOT IN (SELECT id FROM children)",
            ],
            [
                'label' => 'Quotations → Users',
                'query' => "SELECT COUNT(*) as cnt FROM quotations WHERE tenant_id = {$tenantId} AND user_id NOT IN (SELECT id FROM users)",
            ],
            [
                'label' => 'Quotations → Centres',
                'query' => "SELECT COUNT(*) as cnt FROM quotations WHERE tenant_id = {$tenantId} AND centre_id NOT IN (SELECT id FROM centres)",
            ],
            [
                'label' => 'Quotation Items → Quotations',
                'query' => "SELECT COUNT(*) as cnt FROM quotation_items WHERE quotation_id NOT IN (SELECT id FROM quotations WHERE tenant_id = {$tenantId})",
            ],
            [
                'label' => 'Quotation Items → Products',
                'query' => "SELECT COUNT(*) as cnt FROM quotation_items qi INNER JOIN quotations q ON qi.quotation_id = q.id WHERE q.tenant_id = {$tenantId} AND qi.product_id IS NOT NULL AND qi.product_id NOT IN (SELECT id FROM products)",
            ],
            [
                'label' => 'Quotation Items → Children',
                'query' => "SELECT COUNT(*) as cnt FROM quotation_items qi INNER JOIN quotations q ON qi.quotation_id = q.id WHERE q.tenant_id = {$tenantId} AND qi.child_id IS NOT NULL AND qi.child_id NOT IN (SELECT id FROM children)",
            ],
            [
                'label' => 'Payments → Users',
                'query' => "SELECT COUNT(*) as cnt FROM payments WHERE tenant_id = {$tenantId} AND user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)",
            ],
            [
                'label' => 'Invoice-Payment Pivot → Invoices',
                'query' => 'SELECT COUNT(*) as cnt FROM invoice_payment WHERE invoice_id NOT IN (SELECT id FROM invoices)',
            ],
            [
                'label' => 'Invoice-Payment Pivot → Payments',
                'query' => 'SELECT COUNT(*) as cnt FROM invoice_payment WHERE payment_id NOT IN (SELECT id FROM payments)',
            ],
            [
                'label' => 'Child Enrolments → Children',
                'query' => "SELECT COUNT(*) as cnt FROM child_enrolments WHERE tenant_id = {$tenantId} AND child_id NOT IN (SELECT id FROM children)",
            ],
            [
                'label' => 'Child Enrolments → Products',
                'query' => "SELECT COUNT(*) as cnt FROM child_enrolments WHERE tenant_id = {$tenantId} AND product_id NOT IN (SELECT id FROM products)",
            ],
            [
                'label' => 'Child Enrolments → Centres',
                'query' => "SELECT COUNT(*) as cnt FROM child_enrolments WHERE tenant_id = {$tenantId} AND centre_id NOT IN (SELECT id FROM centres)",
            ],
            [
                'label' => 'Tenant-User → Users',
                'query' => "SELECT COUNT(*) as cnt FROM tenant_user WHERE tenant_id = {$tenantId} AND user_id NOT IN (SELECT id FROM users)",
            ],
            [
                'label' => 'Tenant-Child → Children',
                'query' => "SELECT COUNT(*) as cnt FROM tenant_child WHERE tenant_id = {$tenantId} AND child_id NOT IN (SELECT id FROM children)",
            ],
            [
                'label' => 'Centre-User → Users',
                'query' => 'SELECT COUNT(*) as cnt FROM centre_user WHERE user_id NOT IN (SELECT id FROM users)',
            ],
            [
                'label' => 'Child-User → Children',
                'query' => 'SELECT COUNT(*) as cnt FROM child_user WHERE child_id NOT IN (SELECT id FROM children)',
            ],
            [
                'label' => 'Child-User → Users',
                'query' => 'SELECT COUNT(*) as cnt FROM child_user WHERE user_id NOT IN (SELECT id FROM users)',
            ],
        ];

        $rows = [];
        foreach ($checks as $check) {
            $count = DB::selectOne($check['query'])->cnt;
            $status = $count === 0 ? 'PASS' : 'FAIL';
            $rows[] = [$check['label'], $count, $status];

            $this->trackResult('FK Integrity', $status === 'PASS', false,
                $status !== 'PASS' ? "{$check['label']}: {$count} dangling references" : null);
        }

        $this->table(['Relationship', 'Dangling Records', 'Status'], $rows);
        $this->newLine();
    }

    /**
     * Validate financial data integrity — invoice totals, payment amounts, etc.
     */
    private function validateFinancialIntegrity(int $tenantId): void
    {
        $this->info('--- 3. Financial Data Integrity ---');

        $rows = [];

        // Check 1: Invoice total vs sum of items
        $mismatchedInvoices = DB::selectOne("
            SELECT COUNT(*) as cnt FROM invoices i
            WHERE i.tenant_id = {$tenantId}
            AND i.total_amount != (
                SELECT COALESCE(SUM(ii.total), 0) FROM invoice_items ii WHERE ii.invoice_id = i.id
            )
        ")->cnt;
        $status = $mismatchedInvoices === 0 ? 'PASS' : 'WARN';
        $rows[] = ['Invoice total vs item sum', $mismatchedInvoices, $status];
        $this->trackResult('Financial', $status === 'PASS', $status === 'WARN',
            $status !== 'PASS' ? "{$mismatchedInvoices} invoices have total_amount != sum of items" : null);

        $mismatchedQuotations = DB::selectOne("\n            SELECT COUNT(*) as cnt FROM quotations q\n            WHERE q.tenant_id = {$tenantId}\n            AND q.total != (\n                SELECT COALESCE(SUM(qi.total), 0) FROM quotation_items qi WHERE qi.quotation_id = q.id\n            )\n        ")->cnt;
        $status = $mismatchedQuotations === 0 ? 'PASS' : 'WARN';
        $rows[] = ['Quotation total vs item sum', $mismatchedQuotations, $status];
        $this->trackResult('Financial', $status === 'PASS', $status === 'WARN',
            $status !== 'PASS' ? "{$mismatchedQuotations} quotations have total != sum of items" : null);

        // Check 2: Invoices marked PAID with no payments
        $paidNoPayments = DB::selectOne("
            SELECT COUNT(*) as cnt FROM invoices i
            WHERE i.tenant_id = {$tenantId}
            AND i.status = 'paid'
            AND i.id NOT IN (SELECT invoice_id FROM invoice_payment)
        ")->cnt;
        $status = $paidNoPayments === 0 ? 'PASS' : 'WARN';
        $rows[] = ['PAID invoices with no payments', $paidNoPayments, $status];
        $this->trackResult('Financial', $status === 'PASS', $status === 'WARN',
            $status !== 'PASS' ? "{$paidNoPayments} PAID invoices have no payment records" : null);

        // Check 3: Payments with zero or negative amounts
        $zeroPayments = DB::selectOne("
            SELECT COUNT(*) as cnt FROM payments
            WHERE tenant_id = {$tenantId} AND amount <= 0
        ")->cnt;
        $status = $zeroPayments === 0 ? 'PASS' : 'WARN';
        $rows[] = ['Payments with zero/negative amount', $zeroPayments, $status];
        $this->trackResult('Financial', $status === 'PASS', $status === 'WARN',
            $status !== 'PASS' ? "{$zeroPayments} payments have zero or negative amounts" : null);

        // Check 4: Invoice payment pivot amounts vs payment amounts
        $pivotMismatch = DB::selectOne("
            SELECT COUNT(*) as cnt FROM (
                SELECT p.id, p.amount as payment_amount, COALESCE(SUM(ip.amount), 0) as pivot_total
                FROM payments p
                LEFT JOIN invoice_payment ip ON ip.payment_id = p.id
                WHERE p.tenant_id = {$tenantId}
                GROUP BY p.id, p.amount
                HAVING payment_amount != pivot_total
            ) as mismatched
        ")->cnt;
        $status = $pivotMismatch === 0 ? 'PASS' : 'WARN';
        $rows[] = ['Payment amount vs pivot total', $pivotMismatch, $status];
        $this->trackResult('Financial', $status === 'PASS', $status === 'WARN',
            $status !== 'PASS' ? "{$pivotMismatch} payments have amount != sum of pivot amounts" : null);

        // Check 5: Legacy vs current total financial amounts
        $legacyPaymentTotal = DB::connection('legacy')->selectOne("
            SELECT COALESCE(SUM(paid_amount), 0) as total FROM `1_transactions`
            WHERE type = 'payment' AND deleted_at IS NULL
        ")->total;
        $currentPaymentTotal = DB::selectOne("
            SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE tenant_id = {$tenantId}
        ")->total;

        $diff = abs($currentPaymentTotal - $legacyPaymentTotal);
        $status = $diff === 0 ? 'PASS' : 'WARN';
        $rows[] = [
            'Total payment amounts',
            'Legacy: '.number_format($legacyPaymentTotal / 100, 2).' | Current: '.number_format($currentPaymentTotal / 100, 2),
            $status,
        ];
        $this->trackResult('Financial', $status === 'PASS', $status === 'WARN',
            $status !== 'PASS' ? 'Payment total difference: '.number_format($diff / 100, 2) : null);

        $this->table(['Check', 'Value', 'Status'], $rows);
        $this->newLine();
    }

    /**
     * Validate orphan records logged during migration.
     */
    private function validateOrphanRecords(int $tenantId): void
    {
        $this->info('--- 4. Orphan Records Summary ---');

        $orphans = DB::table('migration_orphans')
            ->select('source_table', 'reason', DB::raw('COUNT(*) as cnt'))
            ->groupBy('source_table', 'reason')
            ->orderBy('source_table')
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('  No orphan records found.');
            $this->trackResult('Orphans', true, false, null);
        } else {
            $rows = [];
            foreach ($orphans as $orphan) {
                $rows[] = [$orphan->source_table, $orphan->reason, $orphan->cnt];
            }
            $this->table(['Source Table', 'Reason', 'Count'], $rows);
            $totalOrphans = $orphans->sum('cnt');
            $this->trackResult('Orphans', true, true, "{$totalOrphans} total orphan records logged (informational)");
        }

        $this->newLine();
    }

    /**
     * Validate enum/status field consistency.
     */
    private function validateEnumConsistency(int $tenantId): void
    {
        $this->info('--- 5. Enum/Status Consistency ---');

        $rows = [];

        // Invoice statuses
        $invoiceStatuses = DB::table('invoices')
            ->where('tenant_id', $tenantId)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $validInvoiceStatuses = ['draft', 'pending', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'];
        $invalidStatuses = array_diff(array_keys($invoiceStatuses), $validInvoiceStatuses);
        $status = empty($invalidStatuses) ? 'PASS' : 'FAIL';
        $rows[] = ['Invoice statuses', implode(', ', array_map(fn ($s, $c) => "{$s}({$c})", array_keys($invoiceStatuses), $invoiceStatuses)), $status];
        $this->trackResult('Enums', $status === 'PASS', false,
            $status !== 'PASS' ? 'Invalid invoice statuses: '.implode(', ', $invalidStatuses) : null);

        // Payment statuses
        $paymentStatuses = DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $validPaymentStatuses = ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_paid', 'unpaid'];
        $invalidStatuses = array_diff(array_keys($paymentStatuses), $validPaymentStatuses);
        $status = empty($invalidStatuses) ? 'PASS' : 'FAIL';
        $rows[] = ['Payment statuses', implode(', ', array_map(fn ($s, $c) => "{$s}({$c})", array_keys($paymentStatuses), $paymentStatuses)), $status];
        $this->trackResult('Enums', $status === 'PASS', false,
            $status !== 'PASS' ? 'Invalid payment statuses: '.implode(', ', $invalidStatuses) : null);

        // Payment gateways
        $paymentGateways = DB::table('payments')
            ->where('tenant_id', $tenantId)
            ->select('gateway', DB::raw('COUNT(*) as cnt'))
            ->groupBy('gateway')
            ->pluck('cnt', 'gateway')
            ->toArray();

        $validGateways = ['bank_transfer', 'chip', 'billplz', 'stripe', 'cash'];
        $invalidGateways = array_diff(array_keys($paymentGateways), $validGateways);
        $status = empty($invalidGateways) ? 'PASS' : 'FAIL';
        $rows[] = ['Payment gateways', implode(', ', array_map(fn ($s, $c) => "{$s}({$c})", array_keys($paymentGateways), $paymentGateways)), $status];
        $this->trackResult('Enums', $status === 'PASS', false,
            $status !== 'PASS' ? 'Invalid gateways: '.implode(', ', $invalidGateways) : null);

        // Child enrolment statuses
        $enrolmentStatuses = DB::table('child_enrolments')
            ->where('tenant_id', $tenantId)
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $rows[] = ['Child enrolment statuses', implode(', ', array_map(fn ($s, $c) => "{$s}({$c})", array_keys($enrolmentStatuses), $enrolmentStatuses)), 'INFO'];

        $validStateCodes = array_map(
            fn (MalaysianState $state): string => $state->value,
            MalaysianState::cases(),
        );

        $tenantUserIds = DB::table('tenant_user')
            ->select('user_id')
            ->where('tenant_id', $tenantId);

        $stateChecks = [
            'Campus state codes' => DB::table('campuses')->where('tenant_id', $tenantId),
            'Centre state codes' => DB::table('centres')->where('tenant_id', $tenantId),
            'User address state codes' => DB::table('user_addresses')->whereIn('user_id', clone $tenantUserIds),
            'User office state codes' => DB::table('user_office_infos')->whereIn('user_id', clone $tenantUserIds),
            'Family member state codes' => DB::table('family_members')->whereIn('user_id', clone $tenantUserIds),
            'Family member office state codes' => DB::table('family_members')->whereIn('user_id', clone $tenantUserIds),
        ];

        $stateColumns = [
            'Campus state codes' => 'state',
            'Centre state codes' => 'state',
            'User address state codes' => 'state_code',
            'User office state codes' => 'office_state_code',
            'Family member state codes' => 'state_code',
            'Family member office state codes' => 'office_state_code',
        ];

        foreach ($stateChecks as $label => $query) {
            $column = $stateColumns[$label];
            $invalidValues = $this->invalidStateValues($query, $column, $validStateCodes);
            $status = $invalidValues === [] ? 'PASS' : 'FAIL';
            $displayValues = $invalidValues === [] ? 'All values canonical or null' : implode(', ', $invalidValues);

            $rows[] = [$label, $displayValues, $status];
            $this->trackResult(
                'Enums',
                $status === 'PASS',
                false,
                $status !== 'PASS' ? "{$label}: invalid values ".implode(', ', $invalidValues) : null,
            );
        }

        $this->table(['Check', 'Values', 'Status'], $rows);
        $this->newLine();
    }

    /**
     * @param  array<int, string>  $validStateCodes
     * @return array<int, string>
     */
    private function invalidStateValues(Builder $query, string $column, array $validStateCodes): array
    {
        return $query
            ->whereNotNull($column)
            ->whereNotIn($column, $validStateCodes)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    /**
     * Validate media attachments.
     */
    private function validateMediaAttachments(): void
    {
        $this->info('--- 6. Media Attachments Summary ---');

        $mediaCounts = DB::table('media')
            ->select('model_type', 'collection_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('model_type', 'collection_name')
            ->orderBy('model_type')
            ->orderBy('collection_name')
            ->get();

        if ($mediaCounts->isEmpty()) {
            $this->warn('  No media records found. Phase 4 (media migration) may not have been run yet.');
            $this->trackResult('Media', true, true, 'No media records — Phase 4 may not have been run');
        } else {
            $rows = [];
            foreach ($mediaCounts as $media) {
                $modelShort = class_basename(str_replace('\\', '/', $media->model_type));
                $rows[] = [$modelShort, $media->collection_name, number_format($media->cnt)];
            }
            $this->table(['Model', 'Collection', 'Count'], $rows);
            $this->trackResult('Media', true, false, null);
        }

        $this->newLine();
    }

    /**
     * Show migration logs summary.
     */
    private function showMigrationLogsSummary(): void
    {
        $this->info('--- 7. Migration Logs Summary ---');

        $logs = DB::table('migration_logs')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('  No migration log entries found.');

            return;
        }

        $rows = [];
        foreach ($logs as $log) {
            $duration = 'N/A';
            if ($log->started_at && $log->completed_at) {
                $start = \Carbon\Carbon::parse($log->started_at);
                $end = \Carbon\Carbon::parse($log->completed_at);
                $duration = $start->diffForHumans($end, true);
            }

            $rows[] = [
                $log->phase,
                $log->source_table.' → '.$log->target_table,
                number_format($log->total_source),
                number_format($log->total_migrated),
                number_format($log->total_skipped),
                number_format($log->total_errors),
                $log->completed_at ? 'DONE' : 'RUNNING',
                $duration,
            ];
        }

        $this->table(['Phase', 'Tables', 'Source', 'Migrated', 'Skipped', 'Errors', 'Status', 'Duration'], $rows);
        $this->newLine();
    }

    /**
     * Track a validation result.
     */
    private function trackResult(string $category, bool $passed, bool $isWarning = false, ?string $detail = null): void
    {
        if (! isset($this->results[$category])) {
            $this->results[$category] = ['passed' => 0, 'failed' => 0, 'warnings' => 0, 'details' => []];
        }

        if ($passed && ! $isWarning) {
            $this->results[$category]['passed']++;
        } elseif ($isWarning) {
            $this->results[$category]['warnings']++;
        } else {
            $this->results[$category]['failed']++;
        }

        if ($detail) {
            $this->results[$category]['details'][] = $detail;
        }
    }

    /**
     * Print the final validation summary.
     */
    private function printFinalSummary(): void
    {
        $this->info('========================================');
        $this->info('  Final Validation Summary');
        $this->info('========================================');

        $totalPassed = 0;
        $totalFailed = 0;
        $totalWarnings = 0;

        $rows = [];
        foreach ($this->results as $category => $result) {
            $totalPassed += $result['passed'];
            $totalFailed += $result['failed'];
            $totalWarnings += $result['warnings'];

            $overallStatus = $result['failed'] > 0 ? 'FAIL' : ($result['warnings'] > 0 ? 'WARN' : 'PASS');
            $rows[] = [$category, $result['passed'], $result['warnings'], $result['failed'], $overallStatus];
        }

        $this->table(['Category', 'Passed', 'Warnings', 'Failed', 'Overall'], $rows);
        $this->newLine();

        if ($totalFailed > 0) {
            $this->error("VALIDATION FAILED: {$totalFailed} check(s) failed.");
        } elseif ($totalWarnings > 0) {
            $this->warn("VALIDATION PASSED WITH WARNINGS: {$totalWarnings} warning(s) found.");
        } else {
            $this->info('VALIDATION PASSED: All checks passed successfully.');
        }

        // Print details for failures and warnings
        foreach ($this->results as $category => $result) {
            if (! empty($result['details'])) {
                $this->newLine();
                $this->info("  {$category} Details:");
                foreach ($result['details'] as $detail) {
                    $this->line("    - {$detail}");
                }
            }
        }
    }
}
