<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ProcessPaymentAllocationAction;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\TenantChipService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChipPaymentController extends Controller
{
    public function __construct(
        protected ProcessPaymentAllocationAction $processPaymentAllocation,
        protected TenantChipService $chipService,
    ) {}

    public function success(Payment $payment)
    {
        // 1. Fetch CHIP data FIRST (without updating status yet)
        $gatewayData = null;
        try {
            $gatewayData = $this->fetchAndPrepareChipData($payment);

            if ($gatewayData) {
                // Add success-specific callback data
                $gatewayData['success_callback_data'] = [
                    'retrieved_at' => now()->toISOString(),
                    'callback_type' => 'success',
                ];
            }
        } catch (Exception $e) {
            // Log but continue - we'll update without CHIP data if needed
            Log::warning('Failed to fetch CHIP data on success callback', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Get invoices and allocation data
        $invoices = $payment->invoices;
        $invoiceCount = $invoices->count();

        if ($invoiceCount === 0) {
            return $this->redirectToDashboard($payment->user)
                ->with('error', 'No invoices found for this payment.');
        }

        // Check for user-defined allocation from payment record first, then fallback to session
        $userAllocation = $payment->gateway_payment_data['user_allocation'] ?? session('payment_allocation');

        // 3. Process allocation WHILE payment is still PENDING
        $message = 'Payment received but allocation processing encountered an error. Please contact support.';
        try {
            // Process payment allocation with the new action
            $result = $this->processPaymentAllocation->execute(
                payment: $payment,
                invoices: $invoices,
                userAllocation: $userAllocation
            );

            // Generate success message
            $message = $this->processPaymentAllocation->getAllocationMessage(
                $result['allocation_summary']
            );

            // Clear session data after successful processing
            session()->forget(['payment_allocation', 'payment_total', 'payment_invoice_ids']);
        } catch (Exception $e) {
            Log::error('Failed to process payment allocation', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // 4. NOW update payment status to PAID (triggers observer with all data ready)
        $this->updatePaymentStatus($payment, PaymentStatus::PAID, $gatewayData);

        // 5. Redirect to first invoice
        $firstInvoice = $invoices->first();

        return $this->redirectToInvoice($payment->user, $firstInvoice)
            ->with('success', $message);
    }

    public function failure(Payment $payment)
    {
        // Try to fetch the latest CHIP payment data
        try {
            $gatewayData = $this->fetchAndPrepareChipData($payment);

            if ($gatewayData) {
                // Add failure-specific callback data
                $gatewayData['failure_callback_data'] = [
                    'retrieved_at' => now()->toISOString(),
                    'callback_type' => 'failure',
                ];
            }

            $this->updatePaymentStatus($payment, PaymentStatus::FAILED, $gatewayData);
        } catch (Exception $e) {
            // Fallback to just updating status if CHIP API fails
            Log::warning('Failed to fetch CHIP data on failure callback', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $this->updatePaymentStatus($payment, PaymentStatus::FAILED);
        }

        // Clear any session data
        session()->forget(['payment_allocation', 'payment_total', 'payment_invoice_ids']);

        $invoices = $payment->invoices;
        $invoiceCount = $invoices->count();

        if ($invoiceCount === 0) {
            return $this->redirectToDashboard($payment->user)
                ->with('error', 'No invoices found for this payment.');
        }

        $firstInvoice = $invoices->first();
        $message = "Payment failed for {$invoiceCount} invoice(s).";

        return $this->redirectToInvoice($payment->user, $firstInvoice)
            ->with('error', $message);
    }

    public function cancel(Payment $payment)
    {
        // Try to fetch the latest CHIP payment data
        try {
            $gatewayData = $this->fetchAndPrepareChipData($payment);

            if ($gatewayData) {
                // Add cancel-specific callback data
                $gatewayData['cancel_callback_data'] = [
                    'retrieved_at' => now()->toISOString(),
                    'callback_type' => 'cancel',
                ];
            }

            $this->updatePaymentStatus($payment, PaymentStatus::CANCELLED, $gatewayData);
        } catch (Exception $e) {
            // Fallback to just updating status if CHIP API fails
            Log::warning('Failed to fetch CHIP data on cancel callback', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $this->updatePaymentStatus($payment, PaymentStatus::CANCELLED);
        }

        // Clear any session data
        session()->forget(['payment_allocation', 'payment_total', 'payment_invoice_ids']);

        $invoices = $payment->invoices;
        $invoiceCount = $invoices->count();

        if ($invoiceCount === 0) {
            return $this->redirectToDashboard($payment->user)
                ->with('error', 'No invoices found for this payment.');
        }

        $firstInvoice = $invoices->first();
        $message = "Payment was cancelled for {$invoiceCount} invoice(s).";

        return $this->redirectToInvoice($payment->user, $firstInvoice)
            ->with('warning', $message);
    }

    public function webhook(Request $request)
    {
        Log::info('CHIP Webhook received', $request->all());

        try {
            $paymentId = $request->input('id');

            if ($paymentId) {
                $payment = Payment::where('gateway_payment_id', $paymentId)->first();

                if ($payment) {
                    // Store the webhook data in gateway_payment_data
                    $existingData = $payment->gateway_payment_data ?? [];
                    $webhookData = [
                        'webhook_received_at' => now()->toISOString(),
                        'webhook_data' => $request->all(),
                    ];

                    // Merge webhook data with existing data
                    $updatedData = array_merge($existingData, $webhookData);

                    // For this simple implementation, we'll assume webhook means payment is successful
                    // In production, you should verify the payment status with CHIP API
                    $status = $request->input('status', 'paid');

                    if ($status === 'paid') {
                        // Get invoices and process allocation FIRST (before changing status)
                        $payment->load('invoices');

                        if ($payment->invoices->isNotEmpty()) {
                            $userAllocation = $payment->gateway_payment_data['user_allocation'] ?? null;

                            try {
                                $this->processPaymentAllocation->execute(
                                    payment: $payment,
                                    invoices: $payment->invoices,
                                    userAllocation: $userAllocation
                                );

                                Log::info('Payment allocation processed via webhook', [
                                    'payment_id' => $payment->id,
                                    'invoice_count' => $payment->invoices->count(),
                                ]);
                            } catch (Exception $e) {
                                Log::error('Failed to process payment allocation in webhook', [
                                    'payment_id' => $payment->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        // NOW update status to PAID
                        $this->updatePaymentStatus($payment, PaymentStatus::PAID, $updatedData);
                    } elseif ($status === 'failed') {
                        $this->updatePaymentStatus($payment, PaymentStatus::FAILED, $updatedData);
                    } else {
                        // Just update the payment data without changing status
                        $payment->update(['gateway_payment_data' => $updatedData]);
                    }
                }
            }

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            Log::error('CHIP webhook processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    protected function updatePaymentStatus(Payment $payment, PaymentStatus $status, $gatewayData = null): void
    {
        DB::beginTransaction();
        try {
            $updateData = [
                'status' => $status,
                'paid_at' => $status === PaymentStatus::PAID ? now() : null,
            ];

            // If no gateway data provided, try to fetch it from CHIP API
            if ($gatewayData === null && ! empty($payment->gateway_payment_id)) {
                $gatewayData = $this->fetchAndPrepareChipData($payment);
            }

            // If we still don't have gateway data, but payment has existing data,
            // check if it needs chip_data structure enhancement
            if ($gatewayData === null && ! empty($payment->gateway_payment_data)) {
                $existingData = $payment->gateway_payment_data;

                // Check if chip_data is missing or incomplete
                if (! isset($existingData['chip_data']) || empty($existingData['chip_data']['payment_method'])) {
                    Log::info('Attempting to enhance existing CHIP data with chip_data structure', [
                        'payment_id' => $payment->id,
                        'has_chip_data' => isset($existingData['chip_data']),
                        'has_payment_method' => isset($existingData['chip_data']['payment_method']),
                    ]);

                    // Try to fetch fresh data
                    $freshData = $this->fetchAndPrepareChipData($payment);
                    if ($freshData) {
                        $gatewayData = $freshData;
                    }
                }
            }

            // Add gateway payment data if available
            if ($gatewayData !== null) {
                $updateData['gateway_payment_data'] = $gatewayData;
            }

            $payment->update($updateData);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Payment status update failed', [
                'payment_id' => $payment->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch comprehensive CHIP data and prepare it for storage
     */
    protected function fetchAndPrepareChipData(Payment $payment): ?array
    {
        try {
            $tenant = $payment->tenant;

            if (! $tenant) {
                throw new \RuntimeException('Payment tenant could not be resolved.');
            }

            $chipPurchase = $this->chipService->getPurchase($tenant, $payment->gateway_payment_id);

            if (! $chipPurchase) {
                Log::warning('No CHIP purchase data found', [
                    'payment_id' => $payment->id,
                    'gateway_payment_id' => $payment->gateway_payment_id,
                ]);

                return null;
            }

            $existingData = $payment->gateway_payment_data ?? [];

            // Comprehensive chip_data structure with robust field extraction
            $chipData = [
                'id' => $chipPurchase->id ?? null,
                'status' => $chipPurchase->status ?? null,
                'payment_method' => $this->extractPaymentMethod($chipPurchase),
                'updated_on' => $chipPurchase->updated_on ?? $chipPurchase->viewed_on ?? null,
                'created_on' => $chipPurchase->created_on ?? null,
                'currency' => $this->extractCurrency($chipPurchase),
                'total' => $this->extractTotal($chipPurchase),
                'brand_id' => $chipPurchase->brand_id ?? null,
                'checkout_url' => $chipPurchase->checkout_url ?? null,
                'client_email' => $this->extractClientEmail($chipPurchase),
                'client_name' => $this->extractClientName($chipPurchase),
                'reference' => $chipPurchase->reference ?? null,
                'transaction_id' => $this->extractTransactionId($chipPurchase),
                'bank_name' => $this->extractBankName($chipPurchase),
                'fpx_transaction_id' => $this->extractFpxTransactionId($chipPurchase),
            ];

            // Remove null values to keep data clean
            $chipData = array_filter($chipData, function ($value) {
                return $value !== null;
            });

            // Merge with existing data while preserving callback information
            $gatewayData = array_merge($existingData, [
                'chip_data' => $chipData,
                'last_api_fetch' => now()->toISOString(),
            ]);

            // For backward compatibility, also store key fields at root level
            if (isset($chipData['payment_method'])) {
                $gatewayData['payment_method'] = $chipData['payment_method'];
            }
            if (isset($chipData['status'])) {
                $gatewayData['status'] = $chipData['status'];
            }

            Log::info('Successfully prepared CHIP data', [
                'payment_id' => $payment->id,
                'chip_data_keys' => array_keys($chipData),
                'payment_method' => $chipData['payment_method'] ?? 'N/A',
                'status' => $chipData['status'] ?? 'N/A',
            ]);

            return $gatewayData;

        } catch (Exception $e) {
            Log::warning('Failed to fetch comprehensive CHIP data', [
                'payment_id' => $payment->id,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Extract payment method from various possible locations
     */
    private function extractPaymentMethod($chipPurchase): ?string
    {
        return $chipPurchase->transaction_data?->payment_method ??
               $chipPurchase->payment_method ??
               null;
    }

    /**
     * Extract currency from various possible locations
     */
    private function extractCurrency($chipPurchase): string
    {
        return $chipPurchase->purchase?->currency ??
               $chipPurchase->currency ??
               'MYR';
    }

    /**
     * Extract total amount from various possible locations
     */
    private function extractTotal($chipPurchase): ?int
    {
        return $chipPurchase->purchase?->total ??
               $chipPurchase->total ??
               null;
    }

    /**
     * Extract client email from various possible locations
     */
    private function extractClientEmail($chipPurchase): ?string
    {
        return $chipPurchase->client?->email ??
               $chipPurchase->email ??
               null;
    }

    /**
     * Extract client name from various possible locations
     */
    private function extractClientName($chipPurchase): ?string
    {
        return $chipPurchase->client?->full_name ??
               $chipPurchase->name ??
               null;
    }

    /**
     * Extract transaction ID from various possible locations
     */
    private function extractTransactionId($chipPurchase): ?string
    {
        return $chipPurchase->transaction_data?->id ??
               $chipPurchase->transaction_id ??
               null;
    }

    /**
     * Extract bank name from transaction data
     */
    private function extractBankName($chipPurchase): ?string
    {
        return $chipPurchase->transaction_data?->bank_name ?? null;
    }

    /**
     * Extract FPX transaction ID from transaction data
     */
    private function extractFpxTransactionId($chipPurchase): ?string
    {
        return $chipPurchase->transaction_data?->fpx_transaction_id ?? null;
    }

    /**
     * Redirect to appropriate dashboard based on user role
     */
    private function redirectToDashboard($user)
    {
        if ($user && $user->isAdmin()) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        return redirect()->route('filament.parent.pages.dashboard');
    }

    /**
     * Redirect to appropriate invoice view based on user role
     */
    private function redirectToInvoice($user, $invoice)
    {
        if ($user && $user->isAdmin()) {
            return redirect()->route('filament.admin.resources.invoices.view', [
                'tenant' => $invoice->tenant,
                'record' => $invoice->id,
            ]);
        }

        // For parents, redirect to parent dashboard since they don't have invoice detail view
        return redirect()->route('filament.parent.pages.dashboard');
    }
}
