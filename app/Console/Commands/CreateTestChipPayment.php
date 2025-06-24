<?php

namespace App\Console\Commands;

use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class CreateTestChipPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:create-test-chip {--clean : Clean existing test payments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test CHIP payments for testing the UI display';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('clean')) {
            $this->info('Cleaning existing test payments...');
            Payment::where('reference_no', 'like', 'TEST-CHIP-%')->delete();
            Invoice::where('invoice_number', 'like', 'TEST-%')->delete();
            $this->info('Cleaned test data.');
            return 0;
        }

        $this->info('Creating test CHIP payments...');

        // Check for required data
        $tenant = Tenant::first();
        $centre = Centre::first();
        $user = User::first();

        if (!$tenant || !$centre || !$user) {
            $this->error('Missing required data. Please run database seeders first or create:');
            $this->line('- At least one tenant');
            $this->line('- At least one centre');
            $this->line('- At least one user');
            $this->line('');
            $this->info('You can run: php artisan db:seed (if seeders are available)');
            $this->info('Or create these records manually through the admin panel.');
            return 1;
        }

        try {
            // Create test invoice
            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'centre_id' => $centre->id,
                'user_id' => $user->id,
                'invoice_number' => 'TEST-' . time(),
                'date' => now(),
                'due_at' => now()->addDays(30),
                'status' => InvoiceStatus::PENDING,
                'total_amount' => 10000, // RM 100.00
                'total_discounts' => 0,
                'total' => 10000,
            ]);

            // Create test CHIP payment with detailed gateway data
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'centre_id' => $centre->id,
                'user_id' => $user->id,
                'gateway' => Gateway::CHIP,
                'reference_no' => 'TEST-CHIP-' . time(),
                'gateway_payment_id' => 'chip_test_' . time(),
                'status' => PaymentStatus::PAID,
                'amount' => 10000,
                'description' => 'Test CHIP payment for UI testing',
                'paid_at' => now(),            'gateway_payment_data' => [
                // Main chip_data structure
                'chip_data' => [
                    'id' => 'chip_test_' . time(),
                    'status' => 'paid',
                    'payment_method' => 'fpx',
                    'checkout_url' => 'https://gate.chip-in.asia/test-checkout',
                    'created_on' => now()->subMinutes(30)->toISOString(),
                    'updated_on' => now()->toISOString(),
                    'brand_id' => 'test_brand_123',
                    'currency' => 'MYR',
                    'total' => 10000,
                    'client_email' => $user->email,
                    'client_name' => $user->name,
                    'reference' => 'TEST-CHIP-' . time(),
                    'transaction_id' => 'TXN' . time(),
                    'bank_name' => 'Maybank',
                    'fpx_transaction_id' => 'FPX' . time(),
                ],
                // Legacy root level data for backward compatibility
                'id' => 'chip_test_' . time(),
                'status' => 'paid',
                'checkout_url' => 'https://gate.chip-in.asia/test-checkout',
                'payment_method' => 'fpx',
                'created_on' => now()->subMinutes(30)->toISOString(),
                'updated_on' => now()->toISOString(),
                'brand_id' => 'test_brand_123',
                'client' => [
                    'email' => $user->email,
                    'full_name' => $user->name,
                ],
                'purchase' => [
                    'total' => 10000,
                    'currency' => 'MYR',
                    'products' => [
                        [
                            'name' => 'Payment for Invoice #' . $invoice->invoice_number,
                            'price' => 10000
                        ]
                    ],
                ],
                'stored_at' => now()->subMinutes(30)->toISOString(),
                'success_callback_data' => [
                    'retrieved_at' => now()->subMinutes(5)->toISOString(),
                    'callback_type' => 'success'
                ],
                'webhook_received_at' => now()->subMinutes(3)->toISOString(),
                'webhook_data' => [
                    'id' => 'chip_test_' . time(),
                    'status' => 'paid',
                    'event_type' => 'payment.paid'
                ],
                'last_api_fetch' => now()->subMinutes(1)->toISOString(),
            ]
            ]);

            // Link payment to invoice
            $invoice->payments()->attach($payment->id, [
                'amount' => 10000,
            ]);

            // Update invoice status
            $invoice->update(['status' => InvoiceStatus::PAID]);

            // Create another test payment without detailed data
            $payment2 = Payment::create([
                'tenant_id' => $tenant->id,
                'centre_id' => $centre->id,
                'user_id' => $user->id,
                'gateway' => Gateway::CHIP,
                'reference_no' => 'TEST-CHIP-NO-DATA-' . time(),
                'gateway_payment_id' => 'chip_no_data_' . time(),
                'status' => PaymentStatus::PENDING,
                'amount' => 5000,
                'description' => 'Test CHIP payment without detailed data',
                'paid_at' => null,
                'gateway_payment_data' => null
            ]);

            // Link second payment to invoice
            $invoice->payments()->attach($payment2->id, [
                'amount' => 5000,
            ]);

            $this->info('✅ Test CHIP payments created successfully!');
            $this->info("Invoice ID: {$invoice->id} (Number: {$invoice->invoice_number})");
            $this->info("Payment 1 ID: {$payment->id} (With detailed CHIP data)");
            $this->info("Payment 2 ID: {$payment2->id} (Without detailed CHIP data)");
            $this->info('');
            $this->info('You can now view the invoice to see the CHIP payment data display.');
            $this->info('To clean up, run: php artisan payments:create-test-chip --clean');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error creating test data: ' . $e->getMessage());
            return 1;
        }
    }
}
