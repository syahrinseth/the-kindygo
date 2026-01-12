<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentReceiptPdfService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentReceiptController extends Controller
{
    use AuthorizesRequests;

    protected PaymentReceiptPdfService $pdfService;

    public function __construct(PaymentReceiptPdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Download payment receipt as PDF.
     *
     * @return mixed
     */
    public function downloadReceipt(Payment $payment)
    {
        // Check if user can view this payment
        $this->authorize('view', $payment);

        return $this->pdfService->generateReceiptPdf($payment);
    }

    /**
     * Stream payment receipt as PDF.
     *
     * @return mixed
     */
    public function streamReceipt(Payment $payment)
    {
        // Check if user can view this payment
        $this->authorize('view', $payment);

        return $this->pdfService->streamReceiptPdf($payment);
    }
}
