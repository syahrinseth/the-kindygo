<?php

namespace App\Filament\Resources\Children\Pages;

use App\Enums\ChildStatus;
use App\Filament\Resources\Children\Children\ChildResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewChild extends ViewRecord
{
    protected static string $resource = ChildResource::class;
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = Auth::user();
        
        if ($user && $user->current_tenant_id) {
            $status = $this->record->getStatusAtTenant($user->current_tenant_id);
            
            if ($status) {
                // Populate the pivot_status field with the current status
                $data['pivot_status'] = $status->value;
            } else {
                $data['pivot_status'] = ChildStatus::NEW->value;
            }
        }
        
        return $data;
    }
}
