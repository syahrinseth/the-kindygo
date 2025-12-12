<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Forms\TenantForm;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;


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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(TenantForm::make())
            ->columns(2);
    }
}