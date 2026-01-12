<?php

namespace App\Filament\Admin\Resources\InvoiceItemsLedgers\Pages;

use App\Filament\Admin\Resources\InvoiceItemsLedgers\InvoiceItemsLedgers\InvoiceItemsLedgerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoiceItemsLedger extends ViewRecord
{
    protected static string $resource = InvoiceItemsLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Remove edit action for ledger view - it's read-only
            Action::make('back')
                ->label('Back to Ledger')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray')
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
