<?php

namespace App\Filament\Admin\Resources\Quotations\Pages;

use App\Filament\Admin\Resources\Quotations\QuotationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;
}
