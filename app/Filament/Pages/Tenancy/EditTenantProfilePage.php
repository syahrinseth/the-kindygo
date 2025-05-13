<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Forms\TenantForm;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\EditTenantProfile;


class EditTenantProfilePage extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Company profile';
    }
    
    public function hasLogo(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(TenantForm::make())->columns(2);
    }
}