<?php

it('preselects an invoice when a parent follows the invoice make payment action', function (): void {
    // Expected: the invoice action uses the preselect query parameter consumed by MakePayment.
})->todo('InvoiceResource sends `invoice`, while MakePayment only reads `preselect`.');

it('forces a parent with a pending undertaking onto the registered agreement page', function (): void {
    // Expected: Parent panel middleware redirects to /parent/agreement/pending until accepted.
})->todo('EnsureUndertakingAgreed is not attached to the Parent panel and redirects to the wrong path.');

it('opens every visible admin view action on a registered view page', function (): void {
    // Expected: visible View actions only exist for resources with a registered view route.
})->todo('Several Admin resources expose ViewAction without registering a view page.');

it('allows authorised finance roles to open the finance dashboard', function (): void {
    // Expected: super-admin, admin, and principal users pass the FinanceDashboard policy.
})->todo('FinanceDashboard calls a policy ability without the mapped policy subject and currently returns 403.');

it('allows authorised finance roles to open the invoice item ledger screens', function (): void {
    // Expected: an authorised Admin role can list and view tenant-scoped ledger entries.
})->todo('InvoiceItemsLedger resource authorisation currently returns 403 for an authorised super-admin.');

it('opens the registered admin payment creation screens for authorised users', function (): void {
    // Expected: direct and multi-invoice payment routes are usable by their intended roles.
})->todo('PaymentResource registers both creation routes but canCreate() always returns false.');

it('persists the organisation undertaking requirement from admin settings', function (): void {
    // Expected: saving the toggle updates the tenant and activates agreement enforcement.
})->todo('Tenant does not currently allow or cast require_undertaking_agreement for mass assignment.');

it('allows a registered parent to open the registered child creation page', function (): void {
    // Expected: parents can add a child after registration through the Parent panel.
})->todo('The Parent panel registers a child creation page, but ChildPolicy::create() denies the parent role.');
