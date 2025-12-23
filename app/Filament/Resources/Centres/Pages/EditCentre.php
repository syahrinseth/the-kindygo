<?php

namespace App\Filament\Resources\Centres\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Centres\Centres\CentreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCentre extends EditRecord
{
    protected static string $resource = CentreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
