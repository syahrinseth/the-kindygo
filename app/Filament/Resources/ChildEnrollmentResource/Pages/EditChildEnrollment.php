<?php

namespace App\Filament\Resources\ChildEnrollmentResource\Pages;

use App\Filament\Resources\ChildEnrollmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChildEnrollment extends EditRecord
{
    protected static string $resource = ChildEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(), // temp disabled
        ];
    }
}
