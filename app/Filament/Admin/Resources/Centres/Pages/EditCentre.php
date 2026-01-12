<?php

namespace App\Filament\Admin\Resources\Centres\Pages;

use App\Filament\Admin\Resources\Centres\CentreResource;
use Filament\Actions\DeleteAction;
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
