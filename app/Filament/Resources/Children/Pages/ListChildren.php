<?php

namespace App\Filament\Resources\Children\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Children\Children\ChildResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChildren extends ListRecords
{
    protected static string $resource = ChildResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
