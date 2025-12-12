<?php

namespace App\Filament\Resources\InvoiceItemsLedgerResource\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\InvoiceItemsLedgerResource;
use App\Enums\PaymentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoiceItemsLedgers extends ListRecords
{
    protected static string $resource = InvoiceItemsLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Remove create action for ledger view
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Items'),
            'paid' => Tab::make(PaymentStatus::PAID->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('paid', true)),
            'unpaid' => Tab::make(PaymentStatus::UNPAID->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('paid', false)->where('paid_amount', 0)),
            'partial' => Tab::make(PaymentStatus::PARTIALLY_PAID->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('paid', false)->where('paid_amount', '>', 0)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here for summary statistics
        ];
    }
}
