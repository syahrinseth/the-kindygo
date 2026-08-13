<?php

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super-admin', 'admin', 'principal', 'teacher', 'parent', 'staff'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->tenant = Tenant::factory()->create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
    ]);
});

function createRegistrationRedirectUser(string|array $roles, Tenant $tenant): User
{
    $user = User::factory()->create([
        'profile_completed' => false,
        'registration_step' => 0,
        'current_tenant_id' => $tenant->id,
    ]);
    $user->assignRole($roles);
    $user->tenants()->attach($tenant->id);

    return $user;
}

it('redirects incomplete admin roles from root to the admin panel', function (string $role) {
    $user = createRegistrationRedirectUser($role, $this->tenant);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/admin');
})->with(['super-admin', 'admin', 'principal', 'teacher']);

it('redirects incomplete admin roles away from the parent wizard', function (string $role) {
    $user = createRegistrationRedirectUser($role, $this->tenant);

    $this->actingAs($user)
        ->get(route('tenant.register.form', ['tenant' => $this->tenant->slug]))
        ->assertRedirect('/admin');
})->with(['super-admin', 'admin', 'principal', 'teacher']);

it('gives the admin panel priority for an incomplete dual-role user', function () {
    $user = createRegistrationRedirectUser(['admin', 'parent'], $this->tenant);

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('tenant.register.form', ['tenant' => $this->tenant->slug]))
        ->assertRedirect('/admin');
});

it('continues redirecting incomplete parents to their tenant wizard', function () {
    $user = createRegistrationRedirectUser('parent', $this->tenant);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('tenant.register.form', [
            'tenant' => $this->tenant->slug,
            'step' => 0,
            'email' => $user->email,
        ]));
});

it('keeps incomplete non-parent users out of the parent wizard', function () {
    $user = createRegistrationRedirectUser('staff', $this->tenant);

    $this->actingAs($user)
        ->get(route('tenant.register.form', ['tenant' => $this->tenant->slug]))
        ->assertRedirect('/');

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/dashboard');
});
