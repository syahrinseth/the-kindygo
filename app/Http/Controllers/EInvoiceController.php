<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class EInvoiceController extends Controller
{
    /**
     * Submit an invoice to the LHDN e-Invoice system.
     *
     * @param Request $request
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function submitInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            // Check if invoice is already submitted
            if ($invoice->isEInvoiceSubmitted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice already submitted to e-Invoice system',
                    'data' => $invoice->getEInvoiceStatus()
                ], 400);
            }

            // Submit to LHDN
            $response = $invoice->submitToEInvoice();

            return response()->json([
                'success' => true,
                'message' => 'Invoice submitted successfully to LHDN e-Invoice system',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'einvoice_uuid' => $invoice->einvoice_uuid,
                    'status' => $invoice->getEInvoiceStatus(),
                    'validation_url' => $invoice->getEInvoiceValidationUrl(),
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit invoice to e-Invoice system',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get e-Invoice status for an invoice.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function getStatus(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isEInvoiceSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not submitted to e-Invoice system'
            ], 400);
        }

        try {
            // Refresh status from LHDN
            $response = $invoice->refreshEInvoiceStatus();

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'status' => $invoice->getEInvoiceStatus(),
                    'validation_url' => $invoice->getEInvoiceValidationUrl(),
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get e-Invoice status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an e-Invoice.
     *
     * @param Request $request
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function cancelInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        if (!$invoice->isEInvoiceSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not submitted to e-Invoice system'
            ], 400);
        }

        try {
            $response = $invoice->cancelEInvoice($request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Invoice cancelled successfully',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'status' => $invoice->getEInvoiceStatus(),
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel e-Invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get validation URL for an e-Invoice.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function getValidationUrl(Invoice $invoice): JsonResponse
    {
        if (!$invoice->isEInvoiceSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not submitted to e-Invoice system'
            ], 400);
        }

        $validationUrl = $invoice->getEInvoiceValidationUrl();

        if (!$validationUrl) {
            return response()->json([
                'success' => false,
                'message' => 'Validation URL not available for this invoice'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'validation_url' => $validationUrl,
                'status' => $invoice->getEInvoiceStatus()
            ]
        ]);
    }

    /**
     * Preview e-Invoice data before submission.
     *
     * @param Invoice $invoice
     * @return JsonResponse
     */
    public function previewInvoiceData(Invoice $invoice): JsonResponse
    {
        try {
            $eInvoiceData = $invoice->toEInvoiceFormat();

            return response()->json([
                'success' => true,
                'message' => 'E-Invoice data generated successfully',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'einvoice_data' => $eInvoiceData
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate e-Invoice data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
