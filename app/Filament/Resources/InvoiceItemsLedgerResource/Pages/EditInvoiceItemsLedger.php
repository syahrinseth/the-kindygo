<?php

namespace App\Filament\Resources\InvoiceItemsLedgerResource\Pages;

use App\Filament\Resources\InvoiceItemsLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceItemsLedger extends EditRecord
{
    protected static string $resource = InvoiceItemsLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
