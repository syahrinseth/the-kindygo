<?php

namespace App\Http\Controllers;

use Exception;
use App\Http\Controllers\Controller;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SyahrinSeth\ChipLaravel\ChipService;

class ChipPaymentController extends Controller
{
    public function success(Payment $payment)
    {
        // Try to fetch the latest CHIP payment data
        try {
            $gatewayData = $this->fetchAndPrepareChipData($payment);
            
            if ($gatewayData) {
                // Add success-specific callback data
                $gatewayData['success_callback_data'] = [
                    'retrieved_at' => now()->toISOString(),
                    'callback_type' => 'success'
                ];
            }
            
            $this->updatePaymentStatus($payment, PaymentStatus::PAID, $gatewayData);
        } catch (Exception $e) {
            // Fallback to just updating status if CHIP API fails
            Log::warning('Failed to fetch CHIP data on success callback', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            $this->updatePaymentStatus($payment, PaymentStatus::PAID);
        }
        
        $invoice = $payment->invoices()->first();
        
        if (!$invoice) {
            return redirect()->route('filament.app.pages.dashboard', ['tenant' => $payment->tenant])
                ->with('error', 'Invoice not found.');
        }
        
        return redirect()
            ->route('filament.app.resources.invoices.view', [
                'tenant' => $invoice->tenant,
                'record' => $invoice->id
            ])
            ->with('success', 'Payment completed successfully!');
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
                    'callback_type' => 'failure'
                ];
            }
            
            $this->updatePaymentStatus($payment, PaymentStatus::FAILED, $gatewayData);
        } catch (Exception $e) {
            // Fallback to just updating status if CHIP API fails
            Log::warning('Failed to fetch CHIP data on failure callback', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            $this->updatePaymentStatus($payment, PaymentStatus::FAILED);
        }
        
        $invoice = $payment->invoices()->first();
        
        if (!$invoice) {
            return redirect()->route('filament.app.pages.dashboard', ['tenant' => $payment->tenant])
                ->with('error', 'Invoice not found.');
        }
        
        return redirect()
            ->route('filament.app.resources.invoices.view', [
                'tenant' => $invoice->tenant,
                'record' => $invoice->id
            ])
            ->with('error', 'Payment failed. Please try again.');
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
                    'callback_type' => 'cancel'
                ];
            }
            
            $this->updatePaymentStatus($payment, PaymentStatus::CANCELLED, $gatewayData);
        } catch (Exception $e) {
            // Fallback to just updating status if CHIP API fails
            Log::warning('Failed to fetch CHIP data on cancel callback', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            $this->updatePaymentStatus($payment, PaymentStatus::CANCELLED);
        }
        
        $invoice = $payment->invoices()->first();
        
        if (!$invoice) {
            return redirect()->route('filament.app.pages.dashboard', ['tenant' => $payment->tenant])
                ->with('error', 'Invoice not found.');
        }
        
        return redirect()
            ->route('filament.app.resources.invoices.view', [
                'tenant' => $invoice->tenant,
                'record' => $invoice->id
            ])
            ->with('warning', 'Payment was cancelled.');
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
                'request' => $request->all()
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
            if ($gatewayData === null && !empty($payment->gateway_payment_id)) {
                $gatewayData = $this->fetchAndPrepareChipData($payment);
            }
            
            // If we still don't have gateway data, but payment has existing data, 
            // check if it needs chip_data structure enhancement
            if ($gatewayData === null && !empty($payment->gateway_payment_data)) {
                $existingData = $payment->gateway_payment_data;
                
                // Check if chip_data is missing or incomplete
                if (!isset($existingData['chip_data']) || empty($existingData['chip_data']['payment_method'])) {
                    Log::info('Attempting to enhance existing CHIP data with chip_data structure', [
                        'payment_id' => $payment->id,
                        'has_chip_data' => isset($existingData['chip_data']),
                        'has_payment_method' => isset($existingData['chip_data']['payment_method'])
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

            // Update invoice and invoice items status if payment is successful
            if ($status === PaymentStatus::PAID) {
                $invoice = $payment->invoices()->first();
                if ($invoice) {
                    // Update invoice items paid status
                    $this->updateInvoiceItemsPaidStatus($invoice, $payment);
                    
                    // Recalculate total paid after updating invoice items
                    $totalPaid = $invoice->getTotalPaid();
                    
                    if ($totalPaid >= $invoice->total) {
                        $invoice->update([
                            'status' => InvoiceStatus::PAID,
                        ]);
                    }
                }
            } elseif (in_array($status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED])) {
                // For failed or cancelled payments, ensure invoice items are not marked as paid
                $invoice = $payment->invoices()->first();
                if ($invoice) {
                    $this->updateInvoiceItemsUnpaidStatus($invoice, $payment);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Payment status update failed', [
                'payment_id' => $payment->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update invoice items to reflect paid status when payment is successful
     */
    protected function updateInvoiceItemsPaidStatus($invoice, Payment $payment): void
    {
        try {
            $invoiceItems = $invoice->invoiceItems;
            $paymentAmount = $payment->amount;
            $remainingPayment = $paymentAmount;

            Log::info('Updating invoice items paid status', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'payment_amount' => $paymentAmount,
                'total_items' => $invoiceItems->count()
            ]);

            foreach ($invoiceItems as $item) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $itemTotal = $item->total;
                $currentPaidAmount = $item->paid_amount ?? 0;
                $outstandingAmount = $itemTotal - $currentPaidAmount;

                if ($outstandingAmount > 0) {
                    $paymentForThisItem = min($remainingPayment, $outstandingAmount);
                    $newPaidAmount = $currentPaidAmount + $paymentForThisItem;
                    $newBalanceAmount = max(0, $itemTotal - $newPaidAmount);
                    $isPaid = $newBalanceAmount == 0;

                    $item->update([
                        'paid_amount' => $newPaidAmount,
                        'balance_amount' => $newBalanceAmount,
                        'paid' => $isPaid,
                    ]);

                    $remainingPayment -= $paymentForThisItem;

                    Log::info('Updated invoice item payment status', [
                        'item_id' => $item->id,
                        'item_total' => $itemTotal,
                        'previous_paid' => $currentPaidAmount,
                        'payment_applied' => $paymentForThisItem,
                        'new_paid_amount' => $newPaidAmount,
                        'new_balance' => $newBalanceAmount,
                        'is_paid' => $isPaid
                    ]);
                }
            }

            if ($remainingPayment > 0) {
                Log::warning('Payment amount exceeds total invoice amount', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'remaining_payment' => $remainingPayment
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to update invoice items paid status', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update invoice items to reflect unpaid status when payment fails or is cancelled
     */
    protected function updateInvoiceItemsUnpaidStatus($invoice, Payment $payment): void
    {
        try {
            $invoiceItems = $invoice->invoiceItems;
            $paymentAmount = $payment->amount;

            Log::info('Reverting invoice items paid status for failed/cancelled payment', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'payment_amount' => $paymentAmount
            ]);

            foreach ($invoiceItems as $item) {
                $itemTotal = $item->total;
                $currentPaidAmount = $item->paid_amount ?? 0;

                // Only revert if this payment amount was previously applied
                if ($currentPaidAmount > 0) {
                    $revertAmount = min($paymentAmount, $currentPaidAmount);
                    $newPaidAmount = max(0, $currentPaidAmount - $revertAmount);
                    $newBalanceAmount = $itemTotal - $newPaidAmount;
                    $isPaid = $newBalanceAmount == 0;

                    $item->update([
                        'paid_amount' => $newPaidAmount,
                        'balance_amount' => $newBalanceAmount,
                        'paid' => $isPaid,
                    ]);

                    Log::info('Reverted invoice item payment status', [
                        'item_id' => $item->id,
                        'item_total' => $itemTotal,
                        'previous_paid' => $currentPaidAmount,
                        'reverted_amount' => $revertAmount,
                        'new_paid_amount' => $newPaidAmount,
                        'new_balance' => $newBalanceAmount,
                        'is_paid' => $isPaid
                    ]);

                    $paymentAmount -= $revertAmount;
                    if ($paymentAmount <= 0) {
                        break;
                    }
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to revert invoice items paid status', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Fetch comprehensive CHIP data and prepare it for storage
     */
    protected function fetchAndPrepareChipData(Payment $payment): ?array
    {
        try {
            $chipService = new ChipService();
            $chipPurchase = $chipService->getPurchase($payment->gateway_payment_id);
            
            if (!$chipPurchase) {
                Log::warning('No CHIP purchase data found', [
                    'payment_id' => $payment->id,
                    'gateway_payment_id' => $payment->gateway_payment_id
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
            $chipData = array_filter($chipData, function($value) {
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
                'status' => $chipData['status'] ?? 'N/A'
            ]);

            return $gatewayData;
            
        } catch (Exception $e) {
            Log::warning('Failed to fetch comprehensive CHIP data', [
                'payment_id' => $payment->id,
                'gateway_payment_id' => $payment->gateway_payment_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
}
