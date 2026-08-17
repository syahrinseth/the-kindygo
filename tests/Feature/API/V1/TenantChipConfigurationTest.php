<?php

use App\Filament\Admin\Pages\EditTenantSettingsPage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->tenant = Tenant::factory()->create([
        'business_id_type' => 'BRN',
        'business_id_value' => '202401234567',
    ]);
    $this->admin = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->admin->tenants()->attach($this->tenant->id);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin, 'sanctum');
});

it('allows an admin to configure CHIP without returning the API key', function (): void {
    $response = $this->putJson('/api/v1/tenants/current/chip-configuration', [
        'enabled' => true,
        'brand_id' => 'brand_123',
        'api_key' => 'chip-secret-key',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.brand_id', 'brand_123')
        ->assertJsonMissing(['api_key' => 'chip-secret-key']);

    $this->tenant->refresh();

    expect($this->tenant->hasChipCredentials())->toBeTrue()
        ->and($this->tenant->chip_api_key)->toBe('chip-secret-key')
        ->and($this->tenant->getRawOriginal('chip_api_key'))->not->toBe('chip-secret-key')
        ->and(Crypt::decryptString($this->tenant->getRawOriginal('chip_api_key')))->toBe('chip-secret-key');
});

it('rejects enabling CHIP without an API key for an unconfigured tenant', function (): void {
    $this->putJson('/api/v1/tenants/current/chip-configuration', [
        'enabled' => true,
        'brand_id' => 'brand_123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['chip_api_key']);
});

it('allows an existing API key to be retained while the brand ID is updated', function (): void {
    $this->tenant->update([
        'chip_brand_id' => 'old_brand',
        'chip_api_key' => 'existing-key',
    ]);

    $this->putJson('/api/v1/tenants/current/chip-configuration', [
        'enabled' => true,
        'brand_id' => 'new_brand',
    ])->assertOk()
        ->assertJsonPath('data.brand_id', 'new_brand');

    $this->tenant->refresh();

    expect($this->tenant->chip_api_key)->toBe('existing-key');
});

it('disables CHIP and clears both credentials', function (): void {
    $this->tenant->update([
        'chip_brand_id' => 'brand_123',
        'chip_api_key' => 'chip-secret-key',
    ]);

    $this->deleteJson('/api/v1/tenants/current/chip-configuration')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.brand_id', null);

    $this->tenant->refresh();

    expect($this->tenant->chip_brand_id)->toBeNull()
        ->and($this->tenant->chip_api_key)->toBeNull()
        ->and($this->tenant->hasChipCredentials())->toBeFalse();
});

it('forbids a non-admin tenant member from managing CHIP configuration', function (): void {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);
    $parent = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $parent->tenants()->attach($this->tenant->id);
    $parent->assignRole('parent');

    $this->actingAs($parent, 'sanctum')
        ->getJson('/api/v1/tenants/current/chip-configuration')
        ->assertForbidden();
});

it('saves CHIP credentials from organisation settings without rehydrating the API key', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(EditTenantSettingsPage::class)
        ->fillForm([
            'chip_enabled' => true,
            'chip_brand_id' => 'settings_brand',
            'chip_api_key' => 'settings-secret-key',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->tenant->refresh();

    expect($this->tenant->chip_brand_id)->toBe('settings_brand')
        ->and($this->tenant->chip_api_key)->toBe('settings-secret-key');
});
