<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public $selectedTenant;

    public function mount(): void
    {
        $this->selectedTenant = Auth::user()->current_tenant_id;
    }

    public function updatedSelectedTenant($value): void
    {
        $user = Auth::user();
        $tenant = $user->tenants()->find($value);

        if ($tenant) {
            $user->current_tenant_id = $tenant->id;
            $user->save();

            // Reload the page to apply the new tenant context
            $this->redirect(request()->header('Referer') ?: route('filament.parent.pages.dashboard'));
        }
    }

    public function render()
    {
        return view('livewire.tenant-switcher', [
            'tenants' => Auth::user()->tenants,
        ]);
    }
}
