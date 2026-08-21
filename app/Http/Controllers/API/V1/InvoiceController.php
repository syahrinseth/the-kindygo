<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\InvoiceResource;
use App\Models\Invoice;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @tags Invoices
 */
#[Group('Invoices', 'Endpoints for viewing invoices and downloading invoice PDFs.')]
class InvoiceController extends Controller
{
    /**
     * Get list of invoices for the authenticated user.
     *
     * Returns a paginated list of invoices for the authenticated parent.
     * Can be filtered by status (pending, paid, overdue, etc.).
     */
    #[Endpoint(operationId: 'invoices.index', title: 'List invoices')]
    #[QueryParameter('status', description: 'Filter by invoice status', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 15)]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $invoices = Invoice::where('user_id', $user->id)
            ->when($request->query('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->withSum([
                'payments as paid_amount' => fn ($query) => $query->where('payments.status', PaymentStatus::PAID->value),
            ], 'invoice_payment.amount')
            ->with(['items', 'centre'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Get details of a specific invoice.
     *
     * Returns detailed information about a specific invoice including line items and payment history.
     * The user must own this invoice.
     */
    #[Endpoint(operationId: 'invoices.show', title: 'Get invoice details')]
    #[PathParameter('invoice', description: 'The invoice ID', type: 'integer')]
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user owns this invoice
        if ($invoice->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        $invoice->load(['items.child', 'items.product', 'payments', 'centre'])
            ->loadSum([
                'payments as paid_amount' => fn ($query) => $query->where('payments.status', PaymentStatus::PAID->value),
            ], 'invoice_payment.amount');

        return response()->json([
            'success' => true,
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Download invoice PDF.
     *
     * Returns a signed URL to download the invoice as a PDF. The URL expires in 30 minutes.
     * The user must own this invoice.
     */
    #[Endpoint(operationId: 'invoices.pdf', title: 'Download invoice PDF')]
    #[PathParameter('invoice', description: 'The invoice ID', type: 'integer')]
    public function pdf(Request $request, Invoice $invoice): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user owns this invoice
        if ($invoice->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        // Generate signed URL for invoice PDF
        // This assumes invoices are stored in a specific location
        $pdfPath = "invoices/{$invoice->id}.pdf";

        if (! Storage::disk('private')->exists($pdfPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice PDF not available.',
            ], 404);
        }

        $url = Storage::disk('private')->temporaryUrl(
            $pdfPath,
            now()->addMinutes(30)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'pdf_url' => $url,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]);
    }
}
