<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Forms\TenantForm;
use App\Models\Tenant;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RegisterTenancyPage extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Company';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(TenantForm::make())
            ->columns(1);
    }

    protected function handleRegistration(array $data): Tenant
    {
        $website = Tenant::create([
            ...$data,
            'user_id' => Auth::id(),
        ]);

        $website->users()->attach(Auth::user());

        return $website;
    }
}
