<?php

namespace App\Filament\Resources\CentreResource\Pages;

use App\Filament\Resources\CentreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCentres extends ListRecords
{
    protected static string $resource = CentreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
