<?php

namespace App\Filament\Resources\LetterOfUndertakings\Pages;

use App\Filament\Resources\LetterOfUndertakings\LetterOfUndertakingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLetterOfUndertakings extends ListRecords
{
    protected static string $resource = LetterOfUndertakingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
