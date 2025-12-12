<?php

namespace App\Filament\Resources\Centres\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Centres\Centres\CentreResource;
use Filament\Actions;
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
