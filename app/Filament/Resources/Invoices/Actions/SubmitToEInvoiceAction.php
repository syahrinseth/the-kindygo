<?php

namespace App\Filament\Resources\Invoices\Actions;

use Exception;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubmitToEInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'submit_to_einvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Submit to E-Invoice')
            ->color('primary')
            ->icon('heroicon-s-cloud-arrow-up')
            ->requiresConfirmation()
            ->modalHeading('Submit Invoice to E-Invoice System')
            ->modalDescription('This will submit the invoice to the LHDN e-Invoice system. Make sure all invoice details are correct before proceeding.')
            ->modalSubmitActionLabel('Submit to E-Invoice')
            ->visible(function (Invoice $record): bool {
                // Only visible for invoices that haven't been submitted to e-invoice yet
                if ($record->einvoice_uuid) {
                    return false;
                }
                
                // Only show for paid or pending invoices (not drafts)
                if ($record->status === InvoiceStatus::DRAFT || $record->status === InvoiceStatus::CANCELLED) {
                    return false;
                }
                
                // Check if user has permission to update the invoice
                return Auth::user()->can('update', $record);
            })
            ->action(function (Invoice $record): void {
                try {
                    $response = $record->submitToEInvoice();
                    
                    // The submitToEInvoice method returns a response array directly or throws an exception
                    // If we reach this point, it means the submission was successful
                    Notification::make()
                        ->title('E-Invoice submitted successfully')
                        ->body('Invoice has been submitted to LHDN e-Invoice system.')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Log::error('E-Invoice submission error', [
                        'invoice_id' => $record->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    Notification::make()
                        ->title('E-Invoice submission failed')
                        ->body('An unexpected error occurred: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    public static function makeHeaderAction(): Action
    {
        return Action::make('submit_to_einvoice')
            ->label('Submit to E-Invoice')
            ->color('primary')
            ->icon('heroicon-s-cloud-arrow-up')
            ->requiresConfirmation()
            ->modalHeading('Submit Invoice to E-Invoice System')
            ->modalDescription('This will submit the invoice to the LHDN e-Invoice system. Make sure all invoice details are correct before proceeding.')
            ->modalSubmitActionLabel('Submit to E-Invoice')
            ->visible(function (Invoice $record): bool {
                // Only visible for invoices that haven't been submitted to e-invoice yet
                if ($record->einvoice_uuid) {
                    return false;
                }
                
                // Only show for paid or pending invoices (not drafts)
                if ($record->status === InvoiceStatus::DRAFT || $record->status === InvoiceStatus::CANCELLED) {
                    return false;
                }
                
                // Check if user has permission to update the invoice
                return Auth::user()->can('update', $record);
            })
            ->action(function (Invoice $record): void {
                try {
                    $response = $record->submitToEInvoice();
                    
                    // The submitToEInvoice method returns a response array directly or throws an exception
                    // If we reach this point, it means the submission was successful
                    Notification::make()
                        ->title('E-Invoice submitted successfully')
                        ->body('Invoice has been submitted to LHDN e-Invoice system.')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Log::error('E-Invoice submission error', [
                        'invoice_id' => $record->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    Notification::make()
                        ->title('E-Invoice submission failed')
                        ->body('An unexpected error occurred: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
