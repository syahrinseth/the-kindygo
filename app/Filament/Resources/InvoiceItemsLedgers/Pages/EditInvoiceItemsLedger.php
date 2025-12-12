<?php

namespace App\Filament\Resources\InvoiceItemsLedgers\Pages;

use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\InvoiceItemsLedgers\InvoiceItemsLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoiceItemsLedger extends EditRecord
{
    protected static string $resource = InvoiceItemsLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
