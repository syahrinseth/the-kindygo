<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class CheckInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check invoices in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Invoice::count();
        $this->info("Total invoices: {$count}");

        $statuses = Invoice::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $this->info('Invoice counts by status:');
        foreach ($statuses as $status) {
            $this->info("- {$status->status}: {$status->count}");
        }

        return Command::SUCCESS;
    }
}
