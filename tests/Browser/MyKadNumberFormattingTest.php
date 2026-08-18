<?php

use App\Models\Tenant;

it('formats a MyKad number while it is entered in the registration wizard', function () {
    $tenant = Tenant::factory()->create();

    visit(route('tenant.register.form', $tenant))
        ->fill('input[wire\\:model="mykad_number"]', '900101011234')
        ->assertValue('input[wire\\:model="mykad_number"]', '900101-01-1234')
        ->assertNoJavascriptErrors();
});
