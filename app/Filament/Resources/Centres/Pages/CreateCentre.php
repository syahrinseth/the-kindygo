<?php

namespace App\Filament\Resources\Centres\Pages;

use App\Filament\Resources\Centres\CentreResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateCentre extends CreateRecord
{
    protected static string $resource = CentreResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Assign the current authenticated user to the newly created centre
        $this->record->users()->attach(Auth::id());
    }
}
