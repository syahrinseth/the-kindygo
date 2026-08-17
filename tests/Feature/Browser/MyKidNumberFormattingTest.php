<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formats a MyKid number while it is entered in the registration wizard', function () {
    $tenant = Tenant::factory()->create();

    visit(route('tenant.register.form', ['tenant' => $tenant, 'step' => 3]))
        ->click('Add Another Child')
        ->fill('input[wire\\:model="children.0.mykid_no"]', '150101010001')
        ->assertValue('input[wire\\:model="children.0.mykid_no"]', '150101-01-0001')
        ->assertNoJavascriptErrors();
});
