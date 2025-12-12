<?php

namespace App\Filament\Resources\ChildEnrolments\Pages;

use App\Filament\Resources\ChildEnrolments\ChildEnrolments\ChildEnrolmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChildEnrolment extends EditRecord
{
    protected static string $resource = ChildEnrolmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(), // temp disabled
        ];
    }
}
