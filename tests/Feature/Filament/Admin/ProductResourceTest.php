<?php

use App\Enums\ProductType;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->assignRole('Admin');
    $this->admin->update(['current_tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders event products in the admin products list', function () {
    $this->actingAs($this->admin);

    $eventProduct = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => ProductType::EVENT,
    ]);

    Livewire::test(ListProducts::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$eventProduct])
        ->assertSee('Event');
});
