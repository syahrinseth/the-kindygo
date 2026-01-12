<?php

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Filament\Admin\Resources\Payments\Payments\PaymentResource;
use App\Models\Invoice;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class BulkPayInvoicesBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk-pay-invoices';
    }

    /**
     * Create a bulk action for paying multiple invoices at once
     */
    public static function make(): BulkAction
    {
        return BulkAction::make(static::getDefaultName())
            ->label('Pay Selected Invoices')
            ->icon('heroicon-o-credit-card')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Pay Multiple Invoices')
            ->modalDescription('Proceed to payment page for the selected invoices?')
            ->action(static::getActionCallback())
            ->deselectRecordsAfterCompletion()
            ->visible(function () {
                $user = Auth::user();

                // Check if user has permission to view invoices
                // We can't check 'update' without an invoice instance
                return $user->can('viewAny', Invoice::class);
            });
    }

    /**
     * Get the action callback
     */
    protected static function getActionCallback(): Closure
    {
        return function (Collection $records) {
            $user = Auth::user();
            $validInvoices = [];
            $skippedCount = 0;

            // Validate each invoice
            foreach ($records as $invoice) {
                // Skip if invoice is not pending or overdue
                if (! in_array($invoice->status, [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE])) {
                    $skippedCount++;

                    continue;
                }

                // Skip if invoice has no balance
                if ($invoice->getRemainingBalance() <= 0) {
                    $skippedCount++;

                    continue;
                }

                // Check if user can update this specific invoice
                if (! $user->can('update', $invoice)) {
                    $skippedCount++;

                    continue;
                }

                $validInvoices[] = $invoice->id;
            }

            // Check if exceeds maximum
            if (count($validInvoices) > 10) {
                Notification::make()
                    ->danger()
                    ->title('Too Many Invoices Selected')
                    ->body('You can only pay up to 10 invoices at once. Please select fewer invoices.')
                    ->persistent()
                    ->send();

                return;
            }

            if (empty($validInvoices)) {
                Notification::make()
                    ->warning()
                    ->title('No Valid Invoices')
                    ->body('None of the selected invoices are eligible for payment. They may already be paid or not belong to you.')
                    ->send();

                return;
            }

            // Show summary notification
            $totalInvoices = count($validInvoices);
            $message = "{$totalInvoices} invoice(s) selected for payment.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} invoice(s) were skipped.";
            }

            Notification::make()
                ->success()
                ->title('Proceeding to Payment')
                ->body($message)
                ->send();

            // Redirect based on user role
            if ($user->hasRole('Parent')) {
                // Parents go to MakePayment page with pre-selection
                $invoiceIds = implode(',', $validInvoices);

                return redirect()->to('/make-payment?preselect='.$invoiceIds);
            } else {
                // Admins/Staff go to create-multi page (FIFO allocation)
                // Store selected invoice IDs in session for create-multi page
                session()->put('multi_payment_invoice_ids', $validInvoices);

                return redirect()->to(PaymentResource::getUrl('create-multi'));
            }
        };
    }
}
