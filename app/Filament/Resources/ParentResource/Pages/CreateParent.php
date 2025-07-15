<?php

namespace App\Filament\Resources\ParentResource\Pages;

use App\Filament\Resources\ParentResource;
use App\Models\UserProfile;
use App\Models\UserAddress;
use App\Models\UserOfficeInfo;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateParent extends CreateRecord
{
    protected static string $resource = ParentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default Parent role
        $parentRole = Role::where('name', 'Parent')->first();
        if ($parentRole) {
            $data['roles'] = [$parentRole->id];
        }
        
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Extract related model data
        $profileData = $data['profile'] ?? [];
        $addressData = $data['userAddress'] ?? [];
        $officeData = $data['officeInfo'] ?? [];

        // Remove related data from main user data
        unset($data['profile'], $data['userAddress'], $data['officeInfo']);

        // Create the user
        $user = static::getModel()::create($data);

        // Create related models
        if (!empty($profileData)) {
            $user->profile()->create($profileData);
        }

        if (!empty($addressData)) {
            $user->userAddress()->create($addressData);
        }

        if (!empty($officeData)) {
            $user->officeInfo()->create($officeData);
        }

        return $user;
    }
}
