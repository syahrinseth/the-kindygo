<?php

namespace App\Filament\Resources\EInvoiceResource\Pages;

use App\Filament\Resources\EInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListEInvoices extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = EInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh_all_statuses')
                ->label('Refresh All Statuses')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Refresh All E-Invoice Statuses')
                ->modalDescription('This will refresh the status of all submitted e-Invoices from LHDN. This may take some time.')
                ->action(function () {
                    $submittedInvoices = $this->getResource()::getEloquentQuery()
                        ->whereNotNull('einvoice_uuid')
                        ->get();
                    
                    $successful = 0;
                    $failed = 0;
                    
                    foreach ($submittedInvoices as $invoice) {
                        try {
                            $invoice->refreshEInvoiceStatus();
                            $successful++;
                        } catch (\Exception $e) {
                            $failed++;
                        }
                    }
                    
                    \Filament\Notifications\Notification::make()
                                ->success()
                                ->title("Refreshed {$successful} statuses successfully. {$failed} failed.")
                                ->duration(5000);
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return EInvoiceResource::getWidgets();
    }
}
