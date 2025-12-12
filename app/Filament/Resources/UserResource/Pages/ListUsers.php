<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => Auth::user()->can('create', UserResource::getModel())),
        ];
    }
}
