<?php

namespace App\Console\Commands;

use App\Enums\Gateway;
use App\Models\Payment;
use Exception;
use Illuminate\Console\Command;
use SyahrinSeth\ChipLaravel\ChipService;

class PopulateChipPaymentData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:populate-chip-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate gateway_payment_data for existing CHIP payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to populate CHIP payment data...');

        $chipPayments = Payment::where('gateway', Gateway::CHIP)
            ->whereNotNull('gateway_payment_id')
            ->whereNull('gateway_payment_data')
            ->get();

        if ($chipPayments->count() === 0) {
            $this->info('No CHIP payments found that need data population.');

            return;
        }

        $this->info("Found {$chipPayments->count()} CHIP payments to process.");

        $chipService = new ChipService;
        $processed = 0;
        $failed = 0;

        foreach ($chipPayments as $payment) {
            try {
                $this->line("Processing payment ID: {$payment->id} (CHIP ID: {$payment->gateway_payment_id})");

                $chipPurchase = $chipService->getPurchase($payment->gateway_payment_id);

                if ($chipPurchase) {
                    $payment->update([
                        'gateway_payment_data' => [
                            // Main chip_data structure with comprehensive information
                            'chip_data' => [
                                'id' => $chipPurchase->id ?? null,
                                'status' => $chipPurchase->status ?? null,
                                'payment_method' => $chipPurchase->transaction_data?->payment_method ??
                                                  $chipPurchase->payment_method ?? null,
                                'updated_on' => $chipPurchase->updated_on ??
                                               $chipPurchase->viewed_on ?? null,
                                'created_on' => $chipPurchase->created_on ?? null,
                                'currency' => $chipPurchase->purchase?->currency ??
                                             $chipPurchase->currency ?? 'MYR',
                                'total' => $chipPurchase->purchase?->total ??
                                          $chipPurchase->total ?? null,
                                'brand_id' => $chipPurchase->brand_id ?? null,
                                'checkout_url' => $chipPurchase->checkout_url ?? null,
                                'client_email' => $chipPurchase->client?->email ??
                                                 $chipPurchase->email ?? null,
                                'client_name' => $chipPurchase->client?->full_name ??
                                                $chipPurchase->name ?? null,
                                'reference' => $chipPurchase->reference ?? null,
                                'transaction_id' => $chipPurchase->transaction_data?->id ?? null,
                                'bank_name' => $chipPurchase->transaction_data?->bank_name ?? null,
                                'fpx_transaction_id' => $chipPurchase->transaction_data?->fpx_transaction_id ?? null,
                            ],
                            // Legacy support - keep some root level data
                            'id' => $chipPurchase->id ?? null,
                            'status' => $chipPurchase->status ?? null,
                            'checkout_url' => $chipPurchase->checkout_url ?? null,
                            'payment_method' => $chipPurchase->transaction_data?->payment_method ??
                                              $chipPurchase->payment_method ?? null,
                            'created_on' => $chipPurchase->created_on ?? null,
                            'updated_on' => $chipPurchase->updated_on ??
                                           $chipPurchase->viewed_on ?? null,
                            'brand_id' => $chipPurchase->brand_id ?? null,
                            'client' => [
                                'email' => $chipPurchase->client?->email ??
                                          $chipPurchase->email ?? null,
                                'full_name' => $chipPurchase->client?->full_name ??
                                              $chipPurchase->name ?? null,
                            ],
                            'purchase' => [
                                'total' => $chipPurchase->purchase?->total ??
                                          $chipPurchase->total ?? null,
                                'currency' => $chipPurchase->purchase?->currency ??
                                             $chipPurchase->currency ?? 'MYR',
                                'products' => $chipPurchase->purchase?->products ?? [],
                            ],
                            'retrieved_at' => now()->toISOString(),
                        ],
                    ]);

                    $processed++;
                    $this->line("✓ Successfully updated payment ID: {$payment->id}");
                } else {
                    $failed++;
                    $this->error("✗ Failed to retrieve CHIP data for payment ID: {$payment->id}");
                }
            } catch (Exception $e) {
                $failed++;
                $this->error("✗ Error processing payment ID: {$payment->id} - {$e->getMessage()}");
            }
        }

        $this->info('Completed processing CHIP payments.');
        $this->info("Successfully processed: {$processed}");
        $this->info("Failed: {$failed}");
    }
}
