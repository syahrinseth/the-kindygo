<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Forms\TenantForm;
use App\Models\Tenant;
use App\Models\Website;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Facades\Auth;

class RegisterTenancyPage extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Company';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(TenantForm::make())->columns(1);
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