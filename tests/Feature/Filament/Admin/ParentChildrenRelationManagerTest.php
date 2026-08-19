<?php

use App\Filament\Admin\Resources\Parents\Pages\EditParent;
use App\Filament\Admin\Resources\Parents\RelationManagers\ChildrenRelationManager;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

    $this->admin = User::factory()->create(['profile_completed' => true]);
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->assignRole('admin');
    $this->admin->update(['current_tenant_id' => $this->tenant->id]);

    $this->parent = User::factory()->create([
        'profile_completed' => true,
        'current_tenant_id' => $this->tenant->id,
    ]);
    $this->parent->tenants()->attach($this->tenant->id);
    $this->parent->assignRole('parent');

    $this->child = Child::factory()->create();
    $this->child->addToTenant($this->tenant);
    $this->parent->children()->attach($this->child->id, [
        'relationship_type' => 'parent',
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($this->admin);
});

it('opens the child view action schema', function (): void {
    Livewire::test(ChildrenRelationManager::class, [
        'ownerRecord' => $this->parent,
        'pageClass' => EditParent::class,
    ])
        ->mountTableAction('view', (string) $this->child->getKey())
        ->assertFormFieldExists('first_name');
});

it('opens the child edit action schema', function (): void {
    Livewire::test(ChildrenRelationManager::class, [
        'ownerRecord' => $this->parent,
        'pageClass' => EditParent::class,
    ])
        ->mountTableAction('edit', (string) $this->child->getKey())
        ->assertFormFieldExists('first_name');
});
