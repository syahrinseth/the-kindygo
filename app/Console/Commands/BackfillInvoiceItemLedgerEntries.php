<?php

namespace App\Console\Commands;

use App\Actions\Ledger\CreateInvoiceItemLedgerEntryAction;
use App\Models\InvoiceItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInvoiceItemLedgerEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:backfill
                            {--dry-run : Show what would be created without actually creating entries}
                            {--limit= : Limit the number of items to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill ledger entries for invoice items that don\'t have initial debit entries';

    /**
     * Execute the console command.
     */
    public function handle(CreateInvoiceItemLedgerEntryAction $createLedgerEntry): int
    {
        $this->info('Starting ledger backfill process...');

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find invoice items without initial ledger entries
        $query = InvoiceItem::query()
            ->whereDoesntHave('ledgerEntries', function ($query) {
                $query->where('ledger_type', 'invoice_item_created');
            })
            ->with(['invoice.tenant', 'invoice.user', 'invoice.centre', 'product']);

        if ($limit) {
            $query->limit((int) $limit);
        }

        $itemsWithoutLedger = $query->get();
        $totalCount = $itemsWithoutLedger->count();

        if ($totalCount === 0) {
            $this->info('No invoice items found without ledger entries. All items are up to date!');

            return Command::SUCCESS;
        }

        $this->info("Found {$totalCount} invoice item(s) without initial ledger entries.");

        if (! $dryRun) {
            $this->newLine();
            if (! $this->confirm('Do you want to proceed with creating ledger entries?', true)) {
                $this->warn('Operation cancelled.');

                return Command::FAILURE;
            }
        }

        $this->newLine();
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($itemsWithoutLedger as $item) {
            try {
                if (! $dryRun) {
                    DB::transaction(function () use ($item, $createLedgerEntry) {
                        // Create initial debit entry
                        $createLedgerEntry->execute($item);
                    });
                }

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = [
                    'item_id' => $item->id,
                    'invoice_number' => $item->invoice->number ?? 'N/A',
                    'error' => $e->getMessage(),
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        if ($dryRun) {
            $this->info("DRY RUN: Would create {$successCount} ledger entries");
        } else {
            $this->info("Successfully created {$successCount} ledger entries");
        }

        if ($errorCount > 0) {
            $this->error("Failed to create {$errorCount} ledger entries");
            $this->newLine();
            $this->warn('Errors:');

            foreach ($errors as $error) {
                $this->line("  - Invoice Item #{$error['item_id']} (Invoice: {$error['invoice_number']}): {$error['error']}");
            }
        }

        $this->newLine();
        $this->info('Backfill process completed!');

        return Command::SUCCESS;
    }
}
