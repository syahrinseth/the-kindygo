<?php

namespace App\Filament\Admin\Resources\InvoiceItemsLedgers\Pages;

use App\Filament\Admin\Resources\InvoiceItemsLedgers\InvoiceItemsLedgers\InvoiceItemsLedgerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoiceItemsLedger extends CreateRecord
{
    protected static string $resource = InvoiceItemsLedgerResource::class;
}
