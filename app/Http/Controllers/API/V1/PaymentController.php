<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Payment\MakePaymentAction;
use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\CreatePaymentRequest;
use App\Http\Resources\API\V1\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Payments
 */
#[Group('Payments', 'Endpoints for creating payments and viewing payment history.')]
class PaymentController extends Controller
{
    public function __construct(
        protected MakePaymentAction $makePayment
    ) {}

    /**
     * Get list of payments for the authenticated user.
     *
     * Returns a paginated list of all payments made by the authenticated parent.
     */
    #[Endpoint(operationId: 'payments.index', title: 'List payments')]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'int', default: 15)]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $payments = Payment::where('user_id', $user->id)
            ->with(['invoices'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Create a new payment.
     *
     * Initiates a payment for one or more invoices. Returns a checkout URL for payment gateways
     * that require redirection (e.g., Chip, FPX). The mobile app should open this URL in a WebView.
     */
    #[Endpoint(operationId: 'payments.store', title: 'Create payment')]
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $request->validated();

        // Get invoices
        $invoiceIds = $validated['invoice_ids'];
        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->where('user_id', $user->id)
            ->get();

        if ($invoices->count() !== count($invoiceIds)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more invoices not found.',
            ], 404);
        }

        // Prepare invoice data for payment action
        $invoiceData = $invoices->map(fn ($inv) => ['id' => $inv->id])->toArray();

        // Determine gateway
        $gateway = Gateway::tryFrom($validated['gateway'] ?? 'chip') ?? Gateway::CHIP;

        // Execute payment
        $result = $this->makePayment->execute(
            user: $user,
            gateway: $gateway,
            totalAmount: $validated['amount'],
            invoices: $invoiceData,
            userAllocation: $validated['allocation'] ?? null,
            additionalData: []
        );

        if (! $result->success) {
            return response()->json([
                'success' => false,
                'message' => $result->message,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initiated successfully.',
            'data' => [
                'payment' => $result->payment ? new PaymentResource($result->payment) : null,
                'checkout_url' => $result->checkoutUrl,
                'requires_redirect' => $result->requiresRedirect,
            ],
        ], 201);
    }

    /**
     * Get details of a specific payment.
     *
     * Returns detailed information about a specific payment including associated invoices.
     * The user must own this payment.
     */
    #[Endpoint(operationId: 'payments.show', title: 'Get payment details')]
    #[PathParameter('payment', description: 'The payment ID', type: 'integer')]
    public function show(Request $request, Payment $payment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user owns this payment
        if ($payment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        $payment->load(['invoices']);

        return response()->json([
            'success' => true,
            'data' => new PaymentResource($payment),
        ]);
    }

    /**
     * Confirm payment status (for polling after redirect).
     *
     * Checks the current status of a payment. Use this endpoint to poll for payment completion
     * after the user returns from the payment gateway redirect.
     * The user must own this payment.
     */
    #[Endpoint(operationId: 'payments.confirm', title: 'Confirm payment status')]
    #[PathParameter('payment', description: 'The payment ID', type: 'integer')]
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user owns this payment
        if ($payment->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $payment->status,
                'is_completed' => $payment->status === PaymentStatus::PAID,
                'payment' => new PaymentResource($payment),
            ],
        ]);
    }
}
