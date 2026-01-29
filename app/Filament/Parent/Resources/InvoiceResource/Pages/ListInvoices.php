<?php

namespace App\Filament\Parent\Resources\InvoiceResource\Pages;

use App\Filament\Parent\Resources\InvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected static ?string $title = 'My Invoices';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
