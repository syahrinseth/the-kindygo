<?php

namespace App\Filament\Resources\Children\Pages;

use App\Enums\ChildStatus;
use App\Filament\Resources\Children\ChildResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditChild extends EditRecord
{
    protected static string $resource = ChildResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = Auth::user();

        if ($user && $user->current_tenant_id) {
            $status = $this->record->getStatusAtTenant($user->current_tenant_id);

            if ($status) {
                // Populate the pivot_status field with the current status
                $data['pivot_status'] = $status->value;
            } else {
                // If the child is not associated with the current tenant yet,
                // create the association with a default status
                $this->record->addToTenant($user->current_tenant_id, ChildStatus::NEW);
                $data['pivot_status'] = ChildStatus::NEW->value;
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle the status update in the form
        if (isset($data['pivot_status'])) {
            $user = Auth::user();

            if ($user && $user->current_tenant_id) {
                $this->record->updateStatusAtTenant(
                    $user->current_tenant_id,
                    ChildStatus::from($data['pivot_status'])
                );
            }

            // Remove pivot_status as it's not a direct attribute of the Child model
            unset($data['pivot_status']);
        }

        return $data;
    }
}
