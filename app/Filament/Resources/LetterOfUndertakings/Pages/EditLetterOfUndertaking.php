<?php

namespace App\Filament\Resources\LetterOfUndertakings\Pages;

use App\Actions\Undertaking\DeactivateOtherLettersAction;
use App\Actions\Undertaking\NotifyParentsOfNewLetterAction;
use App\Filament\Resources\LetterOfUndertakings\LetterOfUndertakingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLetterOfUndertaking extends EditRecord
{
    protected static string $resource = LetterOfUndertakingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $letter = $this->record;

        // Check if is_active was changed to true
        $wasActivated = $letter->wasChanged('is_active') && $letter->is_active;

        if ($wasActivated) {
            app(DeactivateOtherLettersAction::class)->execute($letter);
            app(NotifyParentsOfNewLetterAction::class)->execute($letter);
        }
    }
}
