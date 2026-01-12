<?php

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubmitBulkToEInvoiceAction extends BulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'submit_bulk_to_einvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Submit to E-Invoice')
            ->color('primary')
            ->icon('heroicon-s-cloud-arrow-up')
            ->requiresConfirmation()
            ->modalHeading('Submit Invoices to E-Invoice System')
            ->modalDescription('This will submit the selected invoices to the LHDN e-Invoice system. Only eligible invoices will be processed.')
            ->modalSubmitActionLabel('Submit to E-Invoice')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $successCount = 0;
                $failedCount = 0;
                $skippedCount = 0;
                $errors = [];

                foreach ($records as $invoice) {
                    try {
                        // Skip if already submitted
                        if ($invoice->einvoice_uuid) {
                            $skippedCount++;

                            continue;
                        }

                        // Skip if not eligible (draft or cancelled)
                        if ($invoice->status === InvoiceStatus::DRAFT || $invoice->status === InvoiceStatus::CANCELLED) {
                            $skippedCount++;

                            continue;
                        }

                        // Check permission
                        if (! Auth::user()->can('update', $invoice)) {
                            $skippedCount++;

                            continue;
                        }

                        $response = $invoice->submitToEInvoice();

                        // The submitToEInvoice method returns a response array directly or throws an exception
                        // If we reach this point, it means the submission was successful
                        $successCount++;
                    } catch (Exception $e) {
                        $failedCount++;
                        $errors[] = "Invoice {$invoice->number}: ".$e->getMessage();

                        Log::error('Bulk E-Invoice submission error', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }

                // Create summary notification
                $message = [];
                if ($successCount > 0) {
                    $message[] = "{$successCount} invoice(s) submitted successfully";
                }
                if ($failedCount > 0) {
                    $message[] = "{$failedCount} invoice(s) failed";
                }
                if ($skippedCount > 0) {
                    $message[] = "{$skippedCount} invoice(s) skipped";
                }

                $notification = Notification::make()
                    ->title('E-Invoice Bulk Submission Complete');

                if ($failedCount === 0) {
                    $notification->success();
                } elseif ($successCount === 0) {
                    $notification->danger();
                } else {
                    $notification->warning();
                }

                $notification->body(implode(', ', $message))
                    ->send();

                // Show detailed errors if any
                if (! empty($errors)) {
                    Notification::make()
                        ->title('E-Invoice Submission Errors')
                        ->body(implode("\n", array_slice($errors, 0, 5)).(count($errors) > 5 ? "\n... and ".(count($errors) - 5).' more' : ''))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }
}
