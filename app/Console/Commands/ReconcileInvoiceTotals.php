<?php

namespace App\Console\Commands;

use App\Actions\Invoice\UpdateInvoiceTotals;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileInvoiceTotals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:reconcile-totals
                            {--tenant-id= : Reconcile invoices belonging to a single tenant}
                            {--dry-run : Report inconsistencies without updating invoices}
                            {--chunk=500 : Number of invoices to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile stored invoice totals from invoice items';

    /**
     * Execute the console command.
     */
    public function handle(UpdateInvoiceTotals $updateInvoiceTotals): int
    {
        $tenantId = $this->option('tenant-id');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($chunkSize < 1) {
            $this->error('The chunk option must be at least 1.');

            return self::FAILURE;
        }

        $checked = 0;
        $corrected = 0;

        $invoiceTotals = DB::table('invoice_items')
            ->selectRaw('invoice_id, COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(discount * quantity), 0) as total_discounts')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->groupBy('invoice_id');

        $invoices = DB::table('invoices')
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->select('id');

        $checked = $invoices->count();

        DB::table('invoices')
            ->leftJoinSub($invoiceTotals, 'invoice_totals', fn ($join) => $join->on('invoices.id', '=', 'invoice_totals.invoice_id'))
            ->select(['invoices.id', 'invoices.total_items', 'invoices.total_amount', 'invoices.total_discounts', 'invoices.total'])
            ->when($tenantId !== null, fn ($query) => $query->where('invoices.tenant_id', $tenantId))
            ->where(function ($query) {
                $query->whereRaw('invoices.total_items != COALESCE(invoice_totals.total_items, 0)')
                    ->orWhereRaw('invoices.total_amount != COALESCE(invoice_totals.total_amount, 0)')
                    ->orWhereRaw('invoices.total_discounts != COALESCE(invoice_totals.total_discounts, 0)')
                    ->orWhereRaw('invoices.total != COALESCE(invoice_totals.total, 0)');
            })
            ->orderBy('invoices.id')
            ->chunkById($chunkSize, function ($invoices) use ($updateInvoiceTotals, $dryRun, &$corrected): void {
                foreach ($invoices as $invoiceRow) {
                    $invoice = new Invoice;
                    $invoice->setRawAttributes(['id' => $invoiceRow->id], true);
                    $invoice->exists = true;

                    $totals = $updateInvoiceTotals->calculate($invoice);

                    $corrected++;

                    if (! $dryRun) {
                        $invoice->fill($totals)->save();
                    }
                }
            }, 'invoices.id', 'id');

        $unchanged = $checked - $corrected;
        $mode = $dryRun ? 'Would correct' : 'Corrected';

        $this->info("Checked: {$checked}");
        $this->info("{$mode}: {$corrected}");
        $this->info("Unchanged: {$unchanged}");

        return self::SUCCESS;
    }
}
