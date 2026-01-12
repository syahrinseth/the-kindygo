<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Console\Command;

class UpdateOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-overdue-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update pending invoices to overdue status if past due date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue invoices...');

        // Find pending invoices that are past their due date
        $invoices = Invoice::where('status', InvoiceStatus::PENDING)
            ->where('due_at', '<', now())
            ->get();

        $count = $invoices->count();
        $this->info("Found {$count} overdue invoices.");

        if ($count > 0) {
            // Update them to overdue status
            foreach ($invoices as $invoice) {
                $invoice->status = InvoiceStatus::OVERDUE;
                $invoice->save();

                $this->line("Updated Invoice #{$invoice->number} to OVERDUE status.");
            }

            $this->info("Successfully updated {$count} invoices to OVERDUE status.");
        } else {
            $this->info('No invoices need to be updated.');
        }

        return Command::SUCCESS;
    }
}
