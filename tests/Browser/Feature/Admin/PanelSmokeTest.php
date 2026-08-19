<?php

use App\Models\User;

it('loads every registered admin panel screen without browser errors', function (): void {
    $fixture = $this->createAdminPanelFixture('super-admin');

    $screens = [
        [route('filament.admin.pages.dashboard'), 'Dashboard'],
        [route('filament.admin.pages.organisation-settings'), 'Organisation Settings'],
        [route('filament.admin.auth.profile'), 'Profile'],
        [route('filament.admin.resources.campuses.index'), 'Campuses'],
        [route('filament.admin.resources.campuses.create'), 'Create Campus'],
        [route('filament.admin.resources.campuses.edit', $fixture['campus']), 'Edit'],
        [route('filament.admin.resources.centres.index'), 'Centres'],
        [route('filament.admin.resources.centres.create'), 'Create Centre'],
        [route('filament.admin.resources.centres.edit', $fixture['centre']), 'Edit Centre'],
        [route('filament.admin.resources.children.index'), 'Children'],
        [route('filament.admin.resources.children.create'), 'Create Child'],
        [route('filament.admin.resources.children.edit', $fixture['child']), 'Edit'],
        [route('filament.admin.resources.child-enrolments.index'), 'Enrolments'],
        [route('filament.admin.resources.child-enrolments.create'), 'Enrolment Details'],
        [route('filament.admin.resources.child-enrolments.view', $fixture['enrolment']), 'View'],
        [route('filament.admin.resources.child-enrolments.edit', $fixture['enrolment']), 'Edit'],
        [route('filament.admin.resources.invoices.index'), 'Invoices'],
        [route('filament.admin.resources.invoices.create'), 'Create Invoice'],
        [route('filament.admin.resources.invoices.view', $fixture['invoice']), 'View'],
        [route('filament.admin.resources.invoices.edit', $fixture['invoice']), 'Edit'],
        [route('filament.admin.resources.letter-of-undertakings.index'), 'Letter Of Undertakings'],
        [route('filament.admin.resources.letter-of-undertakings.create'), 'Create Letter Of Undertaking'],
        [route('filament.admin.resources.letter-of-undertakings.edit', $fixture['letter']), 'Edit'],
        [route('filament.admin.resources.parents.index'), 'Parents'],
        [route('filament.admin.resources.parents.create'), 'Create Parent'],
        [route('filament.admin.resources.parents.edit', $fixture['parent']), 'Edit'],
        [route('filament.admin.resources.payments.payments.index'), 'Payments'],
        [route('filament.admin.resources.payments.payments.view', $fixture['payment']), 'View'],
        [route('filament.admin.resources.payments.payments.edit', $fixture['payment']), 'Edit'],
        [route('filament.admin.resources.products.index'), 'Products'],
        [route('filament.admin.resources.products.create'), 'Create Product'],
        [route('filament.admin.resources.products.edit', $fixture['product']), 'Edit'],
        [route('filament.admin.resources.quotations.index'), 'Quotations'],
        [route('filament.admin.resources.quotations.create'), 'Create Quotation'],
        [route('filament.admin.resources.quotations.edit', $fixture['quotation']), 'Edit'],
        [route('filament.admin.resources.users.index'), 'Users'],
        [route('filament.admin.resources.users.create'), 'Create User'],
        [route('filament.admin.resources.users.edit', $fixture['admin']), 'Edit'],
    ];

    foreach ($screens as [$url, $heading]) {
        visit($url)
            ->assertSee($heading)
            ->assertNoSmoke();
    }
});

it('redirects guests away from the admin panel', function (): void {
    visit('/admin/dashboard')
        ->assertPathIs('/login')
        ->assertNoJavascriptErrors();
});

it('does not expose admin navigation to parent users', function (): void {
    $fixture = $this->createParentPanelFixture();

    visit('/parent/dashboard')
        ->assertPathIs('/parent/dashboard')
        ->assertDontSee('Tenant Settings')
        ->assertNoJavascriptErrors();

    expect($fixture['parent'])->toBeInstanceOf(User::class);
});

it('renders admin navigation on a mobile viewport', function (): void {
    $this->createAdminPanelFixture();

    visit('/admin/dashboard')
        ->on()->mobile()
        ->assertSee('Dashboard')
        ->assertNoSmoke();
});
