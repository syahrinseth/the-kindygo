<?php

namespace App\Filament\Admin\Resources\Campuses\Pages;

use App\Filament\Admin\Resources\Campuses\CampusResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCampus extends ViewRecord
{
    protected static string $resource = CampusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
