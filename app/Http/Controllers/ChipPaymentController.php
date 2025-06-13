<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChipPaymentController extends Controller
{
    public function success(Payment $payment)
    {
        $this->updatePaymentStatus($payment, PaymentStatus::PAID);
        
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
        $this->updatePaymentStatus($payment, PaymentStatus::FAILED);
        
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
        $this->updatePaymentStatus($payment, PaymentStatus::CANCELLED);
        
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
                    // For this simple implementation, we'll assume webhook means payment is successful
                    // In production, you should verify the payment status with CHIP API
                    $status = $request->input('status', 'paid');
                    
                    if ($status === 'paid') {
                        $this->updatePaymentStatus($payment, PaymentStatus::PAID);
                    } elseif ($status === 'failed') {
                        $this->updatePaymentStatus($payment, PaymentStatus::FAILED);
                    }
                }
            }
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('CHIP webhook processing failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    protected function updatePaymentStatus(Payment $payment, PaymentStatus $status): void
    {
        DB::beginTransaction();
        try {
            $payment->update([
                'status' => $status,
                'paid_at' => $status === PaymentStatus::PAID ? now() : null,
            ]);

            // Update invoice status if payment is successful
            if ($status === PaymentStatus::PAID) {
                $invoice = $payment->invoices()->first();
                if ($invoice) {
                    $totalPaid = $invoice->getTotalPaid();
                    
                    if ($totalPaid >= $invoice->total) {
                        $invoice->update([
                            'status' => InvoiceStatus::PAID,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment status update failed', [
                'payment_id' => $payment->id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }
}
