<?php

use App\Filament\Admin\Resources\LetterOfUndertakings\Pages\CreateLetterOfUndertaking;
use App\Filament\Admin\Resources\LetterOfUndertakings\Pages\EditLetterOfUndertaking;
use App\Models\LetterOfUndertaking;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    // Create roles
    Role::firstOrCreate(['name' => 'admin']);

    $this->admin = User::factory()->create();
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->assignRole('admin');
    $this->admin->update(['current_tenant_id' => $this->tenant->id]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('admin can create letter of undertaking with description', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateLetterOfUndertaking::class)
        ->fillForm([
            'title' => 'Parent Agreement Letter',
            'description' => 'This is a summary of the letter of undertaking for parents.',
            'content' => '<p>This is the full content of the letter.</p>',
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('letters_of_undertaking', [
        'title' => 'Parent Agreement Letter',
        'description' => 'This is a summary of the letter of undertaking for parents.',
        'tenant_id' => $this->tenant->id,
    ]);
});

test('admin can create letter of undertaking without description', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateLetterOfUndertaking::class)
        ->fillForm([
            'title' => 'Parent Agreement Letter',
            'description' => null,
            'content' => '<p>This is the full content of the letter.</p>',
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('letters_of_undertaking', [
        'title' => 'Parent Agreement Letter',
        'description' => null,
        'tenant_id' => $this->tenant->id,
    ]);
});

test('admin can edit letter of undertaking description', function () {
    $this->actingAs($this->admin);

    $letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Original Title',
        'description' => 'Original description',
        'content' => '<p>Original content</p>',
        'created_by' => $this->admin->id,
    ]);

    Livewire::test(EditLetterOfUndertaking::class, ['record' => $letter->id])
        ->fillForm([
            'description' => 'Updated description text',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('letters_of_undertaking', [
        'id' => $letter->id,
        'description' => 'Updated description text',
    ]);
});

test('admin can remove description from letter of undertaking', function () {
    $this->actingAs($this->admin);

    $letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Original Title',
        'description' => 'Original description',
        'content' => '<p>Original content</p>',
        'created_by' => $this->admin->id,
    ]);

    Livewire::test(EditLetterOfUndertaking::class, ['record' => $letter->id])
        ->fillForm([
            'description' => null,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('letters_of_undertaking', [
        'id' => $letter->id,
        'description' => null,
    ]);
});

test('description field appears in create form', function () {
    $this->actingAs($this->admin);

    Livewire::test(CreateLetterOfUndertaking::class)
        ->assertFormFieldExists('description');
});

test('description field appears in edit form', function () {
    $this->actingAs($this->admin);

    $letter = LetterOfUndertaking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->admin->id,
    ]);

    Livewire::test(EditLetterOfUndertaking::class, ['record' => $letter->id])
        ->assertFormFieldExists('description');
});
