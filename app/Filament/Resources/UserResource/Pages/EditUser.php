<?php

namespace App\Filament\Resources\UserResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\UserResource;
use App\Models\UserProfile;
use App\Models\UserAddress;
use App\Models\UserOfficeInfo;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => Auth::user()->can('delete', $this->record)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load related model data into the form
        $user = $this->record;
        
        if ($user->profile) {
            $data['profile'] = $user->profile->toArray();
        }
        
        if ($user->userAddress) {
            $data['userAddress'] = $user->userAddress->toArray();
        }
        
        if ($user->officeInfo) {
            $data['officeInfo'] = $user->officeInfo->toArray();
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract related model data
        $profileData = $data['profile'] ?? [];
        $addressData = $data['userAddress'] ?? [];
        $officeData = $data['officeInfo'] ?? [];

        // Remove related data from main user data
        unset($data['profile'], $data['userAddress'], $data['officeInfo']);

        // Update the user
        $record->update($data);

        // Update or create related models
        if (!empty($profileData)) {
            $record->profile()->updateOrCreate(['user_id' => $record->id], $profileData);
        }

        if (!empty($addressData)) {
            $record->userAddress()->updateOrCreate(['user_id' => $record->id], $addressData);
        }

        if (!empty($officeData)) {
            $record->officeInfo()->updateOrCreate(['user_id' => $record->id], $officeData);
        }

        return $record;
    }
}
