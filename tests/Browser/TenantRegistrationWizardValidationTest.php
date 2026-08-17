<?php

use App\Enums\MalaysianState;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('removes a resolved validation summary before showing the next registration step', function () {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'profile_completed' => false,
        'registration_step' => 1,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole('parent');
    $user->tenants()->attach($tenant);

    $this->actingAs($user);

    visit(route('tenant.register.form', ['tenant' => $tenant, 'step' => 2]))
        ->click('Next Step')
        ->assertSee('Please correct the following errors:')
        ->fill('input[wire\\:model="address"]', 'No. 1, Jalan Ampang')
        ->fill('input[wire\\:model="postal_code"]', '50450')
        ->fill('input[wire\\:model="city"]', 'Kuala Lumpur')
        ->select('select[wire\\:model="state"]', MalaysianState::WP_KUALA_LUMPUR->value)
        ->check('input[wire\\:model="information_confirmed"]')
        ->click('Next Step')
        ->assertSee('Child Information')
        ->assertDontSee('Please correct the following errors:')
        ->assertNoJavascriptErrors();
});
