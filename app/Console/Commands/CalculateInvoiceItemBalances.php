<?php

namespace App\Console\Commands;

use Exception;
use App\Models\InvoiceItem;
use App\Models\Scopes\TenantScope;
use App\Models\Scopes\BelongsToManyTenantScope;
use App\Enums\InvoiceStatus;
use Illuminate\Console\Command;

class CalculateInvoiceItemBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice-items:calculate-balances 
                            {--dry-run : Run without making changes}
                            {--invoice= : Process specific invoice ID}
                            {--chunk=100 : Number of items to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate total and balance amounts for invoice items based on latest data and update paid status based on invoice status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Temporarily disable TenantScope for all related models
        $this->disableTenantScopes();

        $dryRun = $this->option('dry-run');
        $invoiceId = $this->option('invoice');
        $chunkSize = (int) $this->option('chunk');

        $this->info('Starting invoice item balance calculation...');
        $this->warn('TenantScope temporarily disabled for this operation');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $query = InvoiceItem::with(['invoice' => function ($query) {
            $query->withoutGlobalScope(TenantScope::class);
        }, 'product' => function ($query) {
            $query->withoutGlobalScope(TenantScope::class);
        }, 'child' => function ($query) {
            $query->withoutGlobalScope(BelongsToManyTenantScope::class);
        }]);
        
        if ($invoiceId) {
            $query->where('invoice_id', $invoiceId);
            $this->info("Processing invoice ID: {$invoiceId}");
        }

        $totalItems = $query->count();
        $this->info("Total items to process: {$totalItems}");

        $bar = $this->output->createProgressBar($totalItems);
        $bar->start();

        $processedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;

        $query->chunk($chunkSize, function ($items) use (&$processedCount, &$updatedCount, &$errorCount, $bar, $dryRun) {
            foreach ($items as $item) {
                try {
                    $updated = $this->processInvoiceItem($item, $dryRun);
                    if ($updated) {
                        $updatedCount++;
                    }
                    $processedCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    $this->error("Error processing item {$item->id}: " . $e->getMessage());
                }
                
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Processing completed!");
        $this->table(['Metric', 'Count'], [
            ['Processed', $processedCount],
            ['Updated', $updatedCount],
            ['Errors', $errorCount],
        ]);

        if ($dryRun && $updatedCount > 0) {
            $this->warn("Run without --dry-run to apply the {$updatedCount} changes");
        }

        return Command::SUCCESS;
    }

    /**
     * Process a single invoice item.
     *
     * @param InvoiceItem $item
     * @param bool $dryRun
     * @return bool Whether the item was updated
     */
    private function processInvoiceItem(InvoiceItem $item, bool $dryRun): bool
    {
        $originalTotal = $item->total;
        $originalPaidAmount = $item->paid_amount;
        $originalBalanceAmount = $item->balance_amount;
        $originalPaid = $item->paid;

        // Recalculate total based on price, quantity, and discount
        $item->calculateTotal();

        // Initialize paid_amount if null
        if (is_null($item->paid_amount)) {
            $item->paid_amount = 0;
        }

        // Calculate balance amount
        $item->balance_amount = $item->total - $item->paid_amount;

        // Determine paid status based on invoice status
        $invoiceStatus = $item->invoice?->status;

        if ($invoiceStatus === InvoiceStatus::PAID) {
            // If invoice is paid in full, mark all items as paid
            $item->paid_amount = $item->total;
            $item->balance_amount = 0;
            $item->paid = true;
        } else {
            // Set paid status based on balance
            $item->paid = $item->balance_amount <= 0;
        }

        // Check if any values changed
        $hasChanges = (
            $originalTotal !== $item->total ||
            $originalPaidAmount !== $item->paid_amount ||
            $originalBalanceAmount !== $item->balance_amount ||
            $originalPaid !== $item->paid
        );

        if ($hasChanges && !$dryRun) {
            $item->save();
        }

        if ($hasChanges && $this->getOutput()->isVerbose()) {
            $this->line("Updated item {$item->id}:");
            $this->line("  Total: {$originalTotal} → {$item->total}");
            $this->line("  Paid Amount: {$originalPaidAmount} → {$item->paid_amount}");
            $this->line("  Balance: {$originalBalanceAmount} → {$item->balance_amount}");
            $this->line("  Paid: " . ($originalPaid ? 'true' : 'false') . " → " . ($item->paid ? 'true' : 'false'));
            $this->line("  Invoice Status: {$invoiceStatus}");
        }

        return $hasChanges;
    }

    /**
     * Temporarily disable TenantScope for all related models.
     *
     * @return void
     */
    private function disableTenantScopes(): void
    {
        // This method serves as documentation for which models have TenantScope disabled
        // The actual disabling is done in the query builder methods
        
        $modelsAffected = [
            'Invoice' => 'TenantScope disabled in relationship loading',
            'Product' => 'TenantScope disabled in relationship loading', 
            'Child' => 'BelongsToManyTenantScope disabled in relationship loading',
            'Centre' => 'Available without TenantScope through relationships',
            'Campus' => 'Available without TenantScope through relationships',
        ];

        $this->info('TenantScope handling:');
        foreach ($modelsAffected as $model => $note) {
            $this->line("  - {$model}: {$note}");
        }
    }
}
