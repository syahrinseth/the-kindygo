<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Response;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use function Spatie\LaravelPdf\Support\pdf;

class PaymentReceiptPdfService
{
    protected PdfConfigurationService $pdfConfig;

    public function __construct(PdfConfigurationService $pdfConfig)
    {
        $this->pdfConfig = $pdfConfig;
    }

    /**
     * Generate a PDF receipt for a payment.
     *
     * @param Payment $payment
     * @return mixed
     */
    public function generateReceiptPdf(Payment $payment)
    {
        $data = $this->prepareReceiptData($payment);
        $filename = 'payment-receipt-' . $payment->reference_no . '.pdf';

        return pdf()
            ->view('pdf.payment-receipt', $data)
            ->format($this->pdfConfig->getStandardFormat())
            ->margins(...$this->pdfConfig->getStandardMargins())
            ->name($filename)
            ->withBrowsershot(function ($browsershot) {
                $this->pdfConfig->configureBrowsershot($browsershot);
            })
            ->download();
    }

    /**
     * Generate a PDF receipt for a payment and return as stream.
     *
     * @param Payment $payment
     * @return mixed
     */
    public function streamReceiptPdf(Payment $payment)
    {
        $data = $this->prepareReceiptData($payment);
        $filename = 'payment-receipt-' . $payment->reference_no . '.pdf';

        return pdf()
            ->view('pdf.payment-receipt', $data)
            ->format($this->pdfConfig->getStandardFormat())
            ->margins(...$this->pdfConfig->getStandardMargins())
            ->name($filename)
            ->withBrowsershot(function ($browsershot) {
                $this->pdfConfig->configureBrowsershot($browsershot);
            })
            ->stream();
    }

    /**
     * Prepare data for receipt generation.
     *
     * @param Payment $payment
     * @return array
     */
    private function prepareReceiptData(Payment $payment): array
    {
        return [
            'payment' => $payment,
            'invoices' => $payment->invoices()->with(['invoiceItems.child', 'user'])->get(),
            'centre' => $payment->centre,
            'user' => $payment->user,
            'generatedAt' => now(),
        ];
    }
}
