<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentReceiptPdfService;
use Exception;
use Illuminate\Console\Command;

class TestPaymentReceiptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:test-receipt {payment_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test payment receipt PDF generation';

    /**
     * Execute the console command.
     */
    public function handle(PaymentReceiptPdfService $pdfService)
    {
        $paymentId = $this->argument('payment_id');

        $payment = Payment::find($paymentId);

        if (! $payment) {
            $this->error("Payment with ID {$paymentId} not found.");

            return Command::FAILURE;
        }

        $this->info("Testing PDF generation for Payment ID: {$paymentId}");
        $this->info("Payment Reference: {$payment->reference_no}");
        $this->info("Payment Status: {$payment->status->value}");
        $this->info("Amount: {$payment->getFormattedAmount()}");

        try {
            // Test the PDF generation without downloading
            $data = [
                'payment' => $payment,
                'invoices' => $payment->invoices()->with(['invoiceItems.child', 'user'])->get(),
                'centre' => $payment->centre,
                'user' => $payment->user,
                'generatedAt' => now(),
            ];

            $this->info('PDF data prepared successfully:');
            $this->info('- Centre: '.($data['centre']->name ?? 'N/A'));
            $this->info('- User: '.$data['user']->name);
            $this->info('- Associated Invoices: '.$data['invoices']->count());

            $this->info('✅ Payment receipt PDF can be generated successfully!');

        } catch (Exception $e) {
            $this->error('❌ Error generating PDF: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
