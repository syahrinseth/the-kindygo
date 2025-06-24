<?php

namespace App\Console\Commands;

use App\Enums\Gateway;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifyChipEnhancements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chip:verify-enhancements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify CHIP payment enhancements are working correctly';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== CHIP Payment Enhancement Verification ===');
        $this->newLine();

        // Check if Payment model has the new methods
        $this->info('1. Checking Payment Model Methods...');
        $payment = new Payment();
        
        $methods = [
            'getNestedChipData',
            'getChipPaymentMethod', 
            'getChipStatus',
            'getChipClientEmail',
            'getChipTransactionId',
            'getChipBankName',
            'getChipReference'
        ];

        foreach ($methods as $method) {
            if (method_exists($payment, $method)) {
                $this->info("   ✓ {$method}() - Available");
            } else {
                $this->error("   ✗ {$method}() - Missing");
            }
        }

        $this->newLine();

        // Check database structure
        $this->info('2. Checking Database Structure...');
        try {
            $hasColumn = Schema::hasColumn('payments', 'gateway_payment_data');
            if ($hasColumn) {
                $this->info('   ✓ gateway_payment_data column exists');
            } else {
                $this->error('   ✗ gateway_payment_data column missing - run migration');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Database check failed: ' . $e->getMessage());
        }

        $this->newLine();

        // Check existing payments
        $this->info('3. Checking Existing Payments...');
        $totalPayments = Payment::count();
        $chipPayments = Payment::where('gateway', Gateway::CHIP)->count();
        $paymentsWithData = Payment::where('gateway', Gateway::CHIP)
            ->whereNotNull('gateway_payment_data')
            ->count();
        $paymentsWithChipData = Payment::where('gateway', Gateway::CHIP)
            ->whereNotNull('gateway_payment_data')
            ->whereRaw("JSON_EXTRACT(gateway_payment_data, '$.chip_data') IS NOT NULL")
            ->count();

        $this->info("   Total payments: {$totalPayments}");
        $this->info("   CHIP payments: {$chipPayments}");
        $this->info("   CHIP payments with gateway_payment_data: {$paymentsWithData}");
        $this->info("   CHIP payments with chip_data structure: {$paymentsWithChipData}");

        $this->newLine();

        // Check controller enhancements
        $this->info('4. Checking Controller Enhancements...');
        try {
            $controller = new \App\Http\Controllers\ChipPaymentController();
            $reflection = new \ReflectionClass($controller);
            
            $methods = [
                'fetchAndPrepareChipData',
                'extractPaymentMethod',
                'extractCurrency',
                'extractTotal',
                'extractClientEmail',
                'extractClientName',
                'extractTransactionId',
                'extractBankName',
                'extractFpxTransactionId'
            ];

            foreach ($methods as $method) {
                if ($reflection->hasMethod($method)) {
                    $this->info("   ✓ {$method}() - Available");
                } else {
                    $this->error("   ✗ {$method}() - Missing");
                }
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Controller check failed: ' . $e->getMessage());
        }

        $this->newLine();

        // Test helper methods with dummy data
        $this->info('5. Testing Helper Methods with Mock Data...');
        try {
            $mockPayment = new Payment([
                'gateway' => Gateway::CHIP,
                'gateway_payment_data' => [
                    'chip_data' => [
                        'payment_method' => 'fpx',
                        'status' => 'paid',
                        'client_email' => 'test@example.com',
                        'transaction_id' => 'txn_123',
                        'bank_name' => 'Test Bank',
                        'reference' => 'REF123'
                    ],
                    'payment_method' => 'legacy_fpx', // Legacy fallback
                    'status' => 'legacy_paid'
                ]
            ]);

            $this->info("   Payment Method (chip_data): " . ($mockPayment->getChipPaymentMethod() ?: 'N/A'));
            $this->info("   Status (chip_data): " . ($mockPayment->getChipStatus() ?: 'N/A'));
            $this->info("   Client Email: " . ($mockPayment->getChipClientEmail() ?: 'N/A'));
            $this->info("   Transaction ID: " . ($mockPayment->getChipTransactionId() ?: 'N/A'));
            $this->info("   Bank Name: " . ($mockPayment->getChipBankName() ?: 'N/A'));
            $this->info("   Reference: " . ($mockPayment->getChipReference() ?: 'N/A'));

            // Test fallback behavior
            $legacyPayment = new Payment([
                'gateway' => Gateway::CHIP,
                'gateway_payment_data' => [
                    'payment_method' => 'legacy_card',
                    'status' => 'legacy_pending'
                ]
            ]);

            $this->info("   Legacy Payment Method: " . ($legacyPayment->getChipPaymentMethod() ?: 'N/A'));
            $this->info("   Legacy Status: " . ($legacyPayment->getChipStatus() ?: 'N/A'));

        } catch (\Exception $e) {
            $this->error('   ✗ Helper method test failed: ' . $e->getMessage());
        }

        $this->newLine();

        // Summary
        $this->info('=== Verification Summary ===');
        if ($totalPayments > 0 && $chipPayments > 0) {
            if ($paymentsWithChipData < $chipPayments) {
                $this->warn("Consider running: php artisan chip:populate-payment-data");
                $this->warn("To enhance existing CHIP payments with the new structure.");
            } else {
                $this->info("✓ All CHIP payments have the enhanced data structure!");
            }
        } else {
            $this->info("ℹ No CHIP payments found. Structure ready for new payments.");
        }

        $this->newLine();
        $this->info('Enhancement verification completed!');
        
        return 0;
    }
}
