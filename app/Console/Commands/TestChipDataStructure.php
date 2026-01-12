<?php

namespace App\Console\Commands;

use App\Enums\Gateway;
use App\Models\Payment;
use Illuminate\Console\Command;

class TestChipDataStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chip:test-data-structure {payment_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the CHIP payment data structure and helper methods';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $paymentId = $this->argument('payment_id');

        if ($paymentId) {
            $payments = Payment::where('id', $paymentId)->where('gateway', Gateway::CHIP)->get();
        } else {
            $payments = Payment::where('gateway', Gateway::CHIP)->limit(5)->get();
        }

        if ($payments->isEmpty()) {
            $this->error('No CHIP payments found.');

            return 1;
        }

        foreach ($payments as $payment) {
            $this->info("=== Payment ID: {$payment->id} ===");
            $this->info("Gateway Payment ID: {$payment->gateway_payment_id}");
            $this->info("Status: {$payment->status->value}");

            // Test helper methods
            $this->info("\n--- Using Helper Methods ---");
            $this->info('CHIP Status: '.($payment->getChipStatus() ?: 'N/A'));
            $this->info('Payment Method: '.($payment->getChipPaymentMethod() ?: 'N/A'));
            $this->info('Client Email: '.($payment->getChipClientEmail() ?: 'N/A'));
            $this->info('Transaction ID: '.($payment->getChipTransactionId() ?: 'N/A'));
            $this->info('Bank Name: '.($payment->getChipBankName() ?: 'N/A'));
            $this->info('Reference: '.($payment->getChipReference() ?: 'N/A'));

            // Show raw data structure
            $this->info("\n--- Raw Gateway Payment Data ---");
            if ($payment->gateway_payment_data) {
                $data = $payment->gateway_payment_data;

                // Show chip_data structure
                if (isset($data['chip_data'])) {
                    $this->info('chip_data keys: '.implode(', ', array_keys($data['chip_data'])));
                    $this->info('chip_data[payment_method]: '.($data['chip_data']['payment_method'] ?? 'Not set'));
                    $this->info('chip_data[status]: '.($data['chip_data']['status'] ?? 'Not set'));
                } else {
                    $this->warn('No chip_data structure found!');
                }

                // Show legacy root level data
                $this->info('Root level payment_method: '.($data['payment_method'] ?? 'Not set'));
                $this->info('Root level status: '.($data['status'] ?? 'Not set'));

                $this->info('Last API fetch: '.($data['last_api_fetch'] ?? 'Never'));
            } else {
                $this->warn('No gateway payment data found!');
            }

            $this->newLine();
        }

        return 0;
    }
}
