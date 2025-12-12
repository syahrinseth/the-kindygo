<?php

namespace App\Filament\Resources\UserResource\Pages;

use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\UserResource;
use App\Models\UserProfile;
use App\Models\UserAddress;
use App\Models\UserOfficeInfo;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
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
