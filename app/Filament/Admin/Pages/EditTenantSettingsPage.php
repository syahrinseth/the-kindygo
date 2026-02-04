<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Forms\TenantForm;
use App\Models\Tenant;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EditTenantSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.admin.pages.edit-tenant-settings';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Organisation Settings';

    protected static ?string $title = 'Organisation Settings';

    protected static ?string $slug = 'organisation-settings';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::SETTINGS;

    protected static ?int $navigationSort = 100;

    public ?array $data = [];

    protected ?Tenant $tenant = null;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Only show for admin roles
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Only admins can access
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    public function mount(): void
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            Notification::make()
                ->title('No Organisation Selected')
                ->body('Please select an organisation to edit settings.')
                ->warning()
                ->send();

            $this->redirect(Dashboard::getUrl());

            return;
        }

        $this->form->fill($tenant->attributesToArray());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema(TenantForm::make())
            ->statePath('data')
            ->model($this->getTenant());
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant ??= Auth::user()?->currentTenant();
    }

    public function save(): void
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            Notification::make()
                ->title('Error')
                ->body('No organisation found to update.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        // Remove fields that shouldn't be updated
        unset($data['id'], $data['user_id'], $data['personal_tenant'], $data['created_at'], $data['updated_at']);

        $tenant->update($data);

        Notification::make()
            ->title('Settings Saved')
            ->body('Your organisation settings have been updated successfully.')
            ->success()
            ->send();
    }
}
