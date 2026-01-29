<?php

namespace App\Filament\Admin\Resources\Centres\Pages;

use App\Filament\Admin\Resources\Centres\CentreResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCentres extends ListRecords
{
    protected static string $resource = CentreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
