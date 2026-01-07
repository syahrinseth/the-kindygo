<?php

namespace App\Filament\Resources\Invoices\Pages;

use Filament\Actions\CreateAction;
use App\Models\Invoice;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => Auth::user()->can('create', Invoice::class)),
        ];
    }
}
