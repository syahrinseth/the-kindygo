<?php

use App\Enums\ChildStatus;
use App\Models\Centre;
use App\Models\Tenant;
use Illuminate\Support\Facades\Notification;

it('switches organisations from the admin topbar and refreshes tenant scoped data', function (): void {
    $fixture = $this->createAdminPanelFixture();
    $otherTenant = Tenant::factory()->create([
        'name' => 'Second Browser Organisation',
        'slug' => 'second-browser-organisation',
    ]);
    $fixture['admin']->tenants()->attach($otherTenant->id);
    Centre::factory()->create([
        'tenant_id' => $otherTenant->id,
        'campus_id' => null,
        'name' => 'Second Tenant Centre',
    ]);

    visit('/admin/centres')
        ->assertSee('Browser Centre')
        ->assertDontSee('Second Tenant Centre')
        ->select('#tenant-switcher', (string) $otherTenant->id)
        ->wait(1)
        ->assertSee('Second Tenant Centre')
        ->assertDontSee('Browser Centre')
        ->assertNoJavascriptErrors();

});

it('changes a new child to active from the admin children table', function (): void {
    $fixture = $this->createAdminPanelFixture();

    visit('/admin/children')
        ->assertSee('Aisyah Browser')
        ->click('Actions')
        ->click('Activate')
        ->wait(1)
        ->assertSee('Active')
        ->assertNoJavascriptErrors();

    expect($fixture['child']->fresh()->getStatusAtTenant($fixture['tenant']))
        ->toBe(ChildStatus::ACTIVE);
});

it('opens the user invitation workflow with role choices', function (): void {
    Notification::fake();
    $this->createAdminPanelFixture();

    visit('/admin/users')
        ->click('Invite User to Company')
        ->assertSee('Invite User to a Company')
        ->assertSee('Email Address')
        ->assertSee('Role')
        ->assertNoJavascriptErrors();
});

it('exposes the complete child enrolment workflow from the child record', function (): void {
    $fixture = $this->createAdminPanelFixture();

    visit(route('filament.admin.resources.children.edit', $fixture['child']))
        ->assertSee('Basic Information')
        ->assertSee('Enrolments')
        ->click('New child enrolment')
        ->assertSee('Enrolment Details')
        ->assertSee('Billing & Schedule')
        ->assertSee('Additional Products')
        ->assertNoJavascriptErrors();
});

it('shows invoice actions and related items for an authorised admin', function (): void {
    $fixture = $this->createAdminPanelFixture();

    visit(route('filament.admin.resources.invoices.view', $fixture['invoice']))
        ->assertSee($fixture['invoice']->number)
        ->assertSee('Invoice Items')
        ->assertSee('Aisyah Browser')
        ->assertSee('Download PDF')
        ->assertSee('Make Payment')
        ->assertNoJavascriptErrors();
});

it('renders organisation payment settings without revealing the saved api key', function (): void {
    $fixture = $this->createAdminPanelFixture();
    $fixture['tenant']->update([
        'chip_brand_id' => 'browser-brand',
        'chip_api_key' => 'browser-secret-key',
    ]);

    visit('/admin/organisation-settings')
        ->assertSee('CHIP Payment Configuration')
        ->assertDontSee('browser-secret-key')
        ->click('.fi-section:has(.fi-section-header-heading:text-is("CHIP Payment Configuration")) > .fi-section-header')
        ->assertSee('Enable CHIP payments')
        ->assertNoJavascriptErrors();
});
