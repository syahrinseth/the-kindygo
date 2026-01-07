<?php

namespace App\Filament\Resources\LetterOfUndertakings\Pages;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;
use App\Actions\Undertaking\DeactivateOtherLettersAction;
use App\Actions\Undertaking\NotifyParentsOfNewLetterAction;
use App\Filament\Resources\LetterOfUndertakings\LetterOfUndertakingResource;

class CreateLetterOfUndertaking extends CreateRecord
{
    protected static string $resource = LetterOfUndertakingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set tenant_id from current Filament tenant context
        $data['tenant_id'] = Auth::user()?->currentTenant()?->id ?? 0;

        // Set created_by to authenticated user
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $letter = $this->record;

        // If activated, deactivate other letters and notify parents
        if ($letter->is_active) {
            app(DeactivateOtherLettersAction::class)->execute($letter);
            app(NotifyParentsOfNewLetterAction::class)->execute($letter);
        }
    }
}
