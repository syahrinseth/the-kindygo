<?php

namespace App\Filament\Admin\Resources\Campuses\Pages;

use App\Filament\Admin\Resources\Campuses\CampusResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCampus extends EditRecord
{
    protected static string $resource = CampusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
