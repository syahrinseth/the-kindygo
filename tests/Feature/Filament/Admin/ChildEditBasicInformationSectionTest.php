<?php

use App\Filament\Admin\Resources\Children\Pages\EditChild;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['profile_completed' => true]);
    $this->admin->assignRole('admin');
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->update(['current_tenant_id' => $this->tenant->id]);

    $this->child = Child::factory()->create();
    $this->child->addToTenant($this->tenant);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders a basic information section inside the basic information tab', function () {
    $this->actingAs($this->admin);

    $html = Livewire::test(EditChild::class, ['record' => $this->child->id])
        ->assertSuccessful()
        ->html();

    expect(substr_count($html, 'Basic Information'))->toBe(2)
        ->and($html)->toContain('fi-section-header-heading');
});
