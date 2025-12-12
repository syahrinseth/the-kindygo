<?php

namespace App\Filament\Resources\ParentResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ParentResource;
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
