<?php

namespace App\Filament\Admin\Resources\Quotations\Pages;

use App\Filament\Admin\Resources\Quotations\QuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
