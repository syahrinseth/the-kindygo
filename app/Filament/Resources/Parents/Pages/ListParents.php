<?php

namespace App\Filament\Resources\Parents\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\Parents\ParentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListParents extends ListRecords
{
    protected static string $resource = ParentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => Auth::user()->can('create', ParentResource::getModel())),
        ];
    }
}
