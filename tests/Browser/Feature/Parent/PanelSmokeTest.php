<?php

it('loads every registered parent panel screen without browser errors', function (): void {
    $fixture = $this->createParentPanelFixture();
    $fixture['letter']->update(['is_active' => true]);
    $fixture['tenant']->forceFill(['require_undertaking_agreement' => true])->save();

    $screens = [
        [route('filament.parent.pages.dashboard'), 'Dashboard'],
        [route('filament.parent.pages.make-payment'), 'Make Payment'],
        [route('filament.parent.pages.agreement.pending'), 'Agreement Required'],
        [route('filament.parent.auth.profile'), 'Profile'],
        [route('filament.parent.resources.children.index'), 'My Children'],
        [route('filament.parent.resources.children.view', $fixture['child']), 'View Child'],
        [route('filament.parent.resources.children.edit', $fixture['child']), 'Edit Child'],
        [route('filament.parent.resources.invoices.index'), 'Invoices'],
        [route('filament.parent.resources.invoices.view', $fixture['invoice']), $fixture['invoice']->number],
        [route('filament.parent.resources.payments.index'), 'Payments'],
        [route('filament.parent.resources.payments.view', $fixture['payment']), $fixture['payment']->reference_no],
    ];

    foreach ($screens as [$url, $heading]) {
        visit($url)
            ->assertSee($heading)
            ->assertNoSmoke();
    }
});

it('redirects guests away from the parent panel', function (): void {
    visit('/parent/dashboard')
        ->assertPathIs('/login')
        ->assertNoJavascriptErrors();
});

it('renders the parent dashboard and navigation on a mobile viewport', function (): void {
    $this->createParentPanelFixture();

    visit('/parent/dashboard')
        ->on()->mobile()
        ->assertSee('Dashboard')
        ->assertSee('Total Outstanding')
        ->assertNoSmoke();
});
