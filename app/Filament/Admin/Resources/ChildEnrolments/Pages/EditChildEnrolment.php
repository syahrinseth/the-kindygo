<?php

namespace App\Filament\Admin\Resources\ChildEnrolments\Pages;

use App\Filament\Admin\Resources\ChildEnrolments\ChildEnrolmentResource;
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
