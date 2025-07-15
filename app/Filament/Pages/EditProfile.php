<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use App\Filament\Forms\UserForm;
use Illuminate\Support\Facades\Auth;

class EditProfile extends BaseEditProfile
{
    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema(UserForm::make())
                    ->statePath('data')
                    ->model($this->getRecord())
            ),
        ];
    }

    public function getRecord(): \App\Models\User
    {
        return Auth::user()->load(['profile', 'userAddress', 'officeInfo']);
    }

    protected function fillForm(): void
    {
        $user = $this->getRecord();
        
        $data = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        // Handle profile data
        if ($user->profile) {
            $data['profile'] = [
                'phone' => $user->profile->phone,
                'nric' => $user->profile->nric,
                'passport' => $user->profile->passport,
                'occupation' => $user->profile->occupation,
            ];
        }

        // Handle address data
        if ($user->userAddress) {
            $data['userAddress'] = [
                'address' => $user->userAddress->address,
                'address_2' => $user->userAddress->address_2,
                'city' => $user->userAddress->city,
                'postal_code' => $user->userAddress->postal_code,
                'state_code' => $user->userAddress->state_code,
            ];
        }

        // Handle office info data
        if ($user->officeInfo) {
            $data['officeInfo'] = [
                'office_phone' => $user->officeInfo->office_phone,
                'office_address' => $user->officeInfo->office_address,
                'office_address_2' => $user->officeInfo->office_address_2,
                'office_city' => $user->officeInfo->office_city,
                'office_postal_code' => $user->officeInfo->office_postal_code,
                'office_state_code' => $user->officeInfo->office_state_code,
            ];
        }

        $this->form->fill($data);
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $user = $record;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        // Update profile information
        $user->profile()->updateOrCreate([], [
            'phone' => $data['profile']['phone'] ?? null,
            'nric' => $data['profile']['nric'] ?? null,
            'passport' => $data['profile']['passport'] ?? null,
            'occupation' => $data['profile']['occupation'] ?? null,
        ]);

        // Update address information
        $user->userAddress()->updateOrCreate([], [
            'address' => $data['userAddress']['address'] ?? null,
            'address_2' => $data['userAddress']['address_2'] ?? null,
            'city' => $data['userAddress']['city'] ?? null,
            'postal_code' => $data['userAddress']['postal_code'] ?? null,
            'state_code' => $data['userAddress']['state_code'] ?? null,
        ]);

        // Update office information
        $user->officeInfo()->updateOrCreate([], [
            'office_phone' => $data['officeInfo']['office_phone'] ?? null,
            'office_address' => $data['officeInfo']['office_address'] ?? null,
            'office_address_2' => $data['officeInfo']['office_address_2'] ?? null,
            'office_city' => $data['officeInfo']['office_city'] ?? null,
            'office_postal_code' => $data['officeInfo']['office_postal_code'] ?? null,
            'office_state_code' => $data['officeInfo']['office_state_code'] ?? null,
        ]);

        return $user;
    }
}
