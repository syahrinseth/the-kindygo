<?php

use App\Enums\MalaysianState;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('removes a resolved validation summary after completing the preceding registration step', function () {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $centre = Centre::factory()->forTenant($tenant)->create();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'profile_completed' => false,
        'registration_step' => 0,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole('parent');
    $user->tenants()->attach($tenant);

    $this->actingAs($user);

    visit(route('tenant.register.form', ['tenant' => $tenant]))
        ->assertSee('Basic Information')
        ->fill('input[wire\\:model="name"]', 'Nur Aisyah Abdullah')
        ->fill('input[wire\\:model="mykad_number"]', '900101011234')
        ->fill('input[wire\\:model="phone"]', '+60123456789')
        ->fill('input[wire\\:model="email"]', 'nur.aisyah@example.test')
        ->check('input[wire\\:model="centre_ids"][value="'.$centre->id.'"]')
        ->click('Next Step')
        ->assertSee('Personal Details')
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

it('does not duplicate a child without MyKid when returning to and resubmitting step three', function (): void {
    Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'web']);

    $tenant = Tenant::factory()->create();
    $centre = Centre::factory()->forTenant($tenant)->create();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'profile_completed' => false,
        'registration_step' => 0,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole('parent');
    $user->tenants()->attach($tenant);

    $this->actingAs($user);

    visit(route('tenant.register.form', ['tenant' => $tenant]))
        ->fill('input[wire\\:model="name"]', 'Nur Aisyah Abdullah')
        ->fill('input[wire\\:model="mykad_number"]', '900101011234')
        ->fill('input[wire\\:model="phone"]', '+60123456789')
        ->fill('input[wire\\:model="email"]', 'nur.aisyah@example.test')
        ->check('input[wire\\:model="centre_ids"][value="'.$centre->id.'"]')
        ->click('Next Step')
        ->fill('input[wire\\:model="address"]', 'No. 1, Jalan Ampang')
        ->fill('input[wire\\:model="postal_code"]', '50450')
        ->fill('input[wire\\:model="city"]', 'Kuala Lumpur')
        ->select('select[wire\\:model="state"]', MalaysianState::WP_KUALA_LUMPUR->value)
        ->check('input[wire\\:model="information_confirmed"]')
        ->click('Next Step')
        ->click('Add Another Child')
        ->fill('input[wire\\:model="children.0.first_name"]', 'Sarah')
        ->fill('input[wire\\:model="children.0.last_name"]', 'Hassan')
        ->select('select[wire\\:model="children.0.gender"]', 'female')
        ->fill('input[wire\\:model="children.0.date_of_birth"]', '2019-05-20')
        ->click('Next Step')
        ->assertSee('Terms and Conditions')
        ->click('Back')
        ->assertSee('Child Information')
        ->click('Next Step')
        ->assertSee('Terms and Conditions')
        ->assertNoJavascriptErrors();

    expect(Child::withoutGlobalScopes()->count())->toBe(1);
});
