<?php

namespace App\Filament\Resources\Children\Pages;

use App\Enums\ChildStatus;
use App\Filament\Resources\Children\ChildResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChild extends CreateRecord
{
    protected static string $resource = ChildResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // For new records, set the default status
        $data['pivot_status'] = ChildStatus::NEW->value;

        return $data;
    }

    protected function afterCreate(): void
    {
        // After creating the child, associate it with the current tenant
        $child = $this->record;
        $user = Auth::user();

        if ($user && $user->current_tenant_id) {
            // Get the status from the form or use the default NEW status
            $status = data_get($this->data, 'pivot_status')
                ? ChildStatus::from($this->data['pivot_status'])
                : ChildStatus::NEW;

            $child->addToTenant($user->current_tenant_id, $status);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove pivot_status as it's not a direct attribute of the Child model
        unset($data['pivot_status']);

        return $data;
    }
}
