<?php

use App\Enums\MalaysianState;
use App\Models\Centre;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('formats a MyKid number after completing the preceding registration steps', function (): void {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create(['slug' => 'admin-tenant']);
    $centre = Centre::factory()->forTenant($tenant)->create();
    $parent = User::factory()->create([
        'email_verified_at' => now(),
        'profile_completed' => false,
        'registration_step' => 0,
        'current_tenant_id' => $tenant->id,
    ]);
    $parent->assignRole('parent');
    $parent->tenants()->attach($tenant);

    $this->actingAs($parent);

    visit(route('tenant.register.form', ['tenant' => $tenant]))
        ->assertSee('Basic Information')
        ->fill('input[wire\\:model="name"]', 'Nur Aisyah Abdullah')
        ->fill('input[wire\\:model="mykad_number"]', '900101011234')
        ->fill('input[wire\\:model="phone"]', '+60123456789')
        ->fill('input[wire\\:model="email"]', 'nur.aisyah@example.test')
        ->check('input[wire\\:model="centre_ids"][value="'.$centre->id.'"]')
        ->click('Next Step')
        ->assertSee('Personal Details')
        ->fill('input[wire\\:model="address"]', 'No. 1, Jalan Ampang')
        ->fill('input[wire\\:model="postal_code"]', '50450')
        ->fill('input[wire\\:model="city"]', 'Kuala Lumpur')
        ->select('select[wire\\:model="state"]', MalaysianState::WP_KUALA_LUMPUR->value)
        ->check('input[wire\\:model="information_confirmed"]')
        ->click('Next Step')
        ->assertSee('Child Information')
        ->click('Add Another Child')
        ->fill('input[wire\\:model="children.0.mykid_no"]', '150101010001')
        ->assertValue('input[wire\\:model="children.0.mykid_no"]', '150101-01-0001')
        ->assertNoJavascriptErrors();
});
