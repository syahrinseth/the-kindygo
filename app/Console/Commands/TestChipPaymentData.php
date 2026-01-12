<?php

namespace App\Console\Commands;

use App\Enums\Gateway;
use App\Models\Payment;
use Illuminate\Console\Command;

class TestChipPaymentData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:test-chip-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test CHIP payment data functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing CHIP payment data functionality...');

        // Find CHIP payments
        $chipPayments = Payment::where('gateway', Gateway::CHIP)->get();

        if ($chipPayments->count() === 0) {
            $this->info('No CHIP payments found in the database.');

            return;
        }

        $this->info("Found {$chipPayments->count()} CHIP payments.");

        foreach ($chipPayments as $payment) {
            $this->line('----------------------------------------');
            $this->line("Payment ID: {$payment->id}");
            $this->line("Reference: {$payment->reference_no}");
            $this->line("Gateway Payment ID: {$payment->gateway_payment_id}");
            $this->line("Status: {$payment->status->value}");
            $this->line("Amount: {$payment->getFormattedAmount()}");

            if ($payment->isChipPayment()) {
                $this->line('✓ Is CHIP payment');

                $chipData = $payment->getChipData();
                if ($chipData) {
                    $this->line('✓ Has CHIP data');
                    $this->line('  - CHIP Status: '.($payment->getChipStatus() ?? 'N/A'));
                    $this->line('  - Payment Method: '.($payment->getChipPaymentMethod() ?? 'N/A'));
                    $this->line('  - Data stored at: '.($payment->getChipInfo('stored_at') ?? 'N/A'));

                    if ($payment->getChipInfo('client.email')) {
                        $this->line('  - Client Email: '.$payment->getChipInfo('client.email'));
                    }

                    if ($payment->getChipInfo('purchase.total')) {
                        $this->line('  - Purchase Total: '.$payment->getChipInfo('purchase.total'));
                    }
                } else {
                    $this->line('✗ No CHIP data found');
                }
            } else {
                $this->line('✗ Not a CHIP payment');
            }
        }

        $this->info('Test completed.');
    }
}
