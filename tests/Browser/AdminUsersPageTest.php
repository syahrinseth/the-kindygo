<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserAddress;
use Spatie\Permission\Models\Role;

it('loads the admin users page with canonical migrated state data', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create(['profile_completed' => true]);
    $tenant = Tenant::factory()->create(['user_id' => $admin->id]);

    $admin->assignRole('admin');
    $admin->tenants()->attach($tenant->id);
    $admin->update(['current_tenant_id' => $tenant->id]);

    UserAddress::query()->create([
        'user_id' => $admin->id,
        'address' => 'Test address',
        'city' => 'Shah Alam',
        'postal_code' => '40100',
        'state_code' => '10',
    ]);

    $this->actingAs($admin);

    visit('/admin/users')
        ->assertSee('Users')
        ->assertNoJavascriptErrors();
});
