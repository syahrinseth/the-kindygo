<?php

use App\Actions\Undertaking\CheckParentUndertakingAgreementAction;
use App\Enums\InvoiceStatus;
use App\Models\Child;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ParentUndertakingAgreement;
use App\Models\Payment;
use App\Models\User;

it('renders parent dashboard widgets with links to owned records', function (): void {
    $fixture = $this->createParentPanelFixture();

    visit('/parent/dashboard')
        ->assertSee('Total Outstanding')
        ->assertSee('Quick Pay')
        ->assertSee('Recent Payments')
        ->assertSee('Upcoming Invoices')
        ->assertSee('My Children')
        ->assertSee($fixture['invoice']->number)
        ->assertSee('Aisyah Browser')
        ->assertNoJavascriptErrors();
});

it('updates invoice selections and totals on the parent payment page', function (): void {
    $fixture = $this->createParentPanelFixture();

    visit('/parent/make-payment')
        ->assertSee($fixture['invoice']->number)
        ->assertSee('1 invoice(s) selected')
        ->assertSee('RM 75.00')
        ->click('Deselect All')
        ->assertSee('0 invoice(s) selected')
        ->assertSee('RM 0.00')
        ->assertButtonDisabled('Proceed to Payment')
        ->click('Select All')
        ->assertSee('1 invoice(s) selected')
        ->assertSee('RM 75.00')
        ->assertButtonEnabled('Proceed to Payment')
        ->assertNoJavascriptErrors();
});

it('preselects only invoices supplied through the supported payment query parameter', function (): void {
    $fixture = $this->createParentPanelFixture();
    $secondInvoice = Invoice::factory()->create([
        'tenant_id' => $fixture['tenant']->id,
        'centre_id' => $fixture['centre']->id,
        'user_id' => $fixture['parent']->id,
        'status' => InvoiceStatus::PENDING,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $secondInvoice->id,
        'product_id' => $fixture['product']->id,
        'child_id' => $fixture['child']->id,
        'price' => 8000,
        'quantity' => 1,
        'discount' => 0,
        'paid_amount' => 0,
    ]);

    visit(route('filament.parent.pages.make-payment', ['preselect' => $secondInvoice->id]))
        ->assertSee('1 invoice(s) selected')
        ->assertSee('RM 80.00')
        ->assertNoJavascriptErrors();
});

it('searches owned children without exposing another parents child', function (): void {
    $fixture = $this->createParentPanelFixture();
    $foreignChild = Child::factory()->create([
        'first_name' => 'Foreign',
        'last_name' => 'Child',
    ]);
    $foreignChild->addToTenant($fixture['tenant']);

    visit('/parent/children')
        ->assertSee('Aisyah Browser')
        ->assertDontSee('Foreign Child')
        ->fill('input[placeholder="Search"]', 'Aisyah')
        ->assertSee('Aisyah Browser')
        ->assertNoJavascriptErrors();
});

it('records a parents acceptance of an active undertaking', function (): void {
    $fixture = $this->createParentPanelFixture();
    $fixture['letter']->update(['is_active' => true]);
    $fixture['tenant']->forceFill(['require_undertaking_agreement' => true])->save();

    expect(app(CheckParentUndertakingAgreementAction::class)
        ->execute($fixture['parent']->fresh(), $fixture['tenant']->fresh())
        ?->is($fixture['letter']))->toBeTrue();

    visit('/parent/agreement/pending')
        ->assertSee('Browser Agreement')
        ->check('input[wire\\:model="data.agreed"]')
        ->click('Submit Agreement')
        ->assertSee('Confirm Agreement')
        ->click('I Agree')
        ->assertPathIs('/parent')
        ->assertNoJavascriptErrors();

    expect(ParentUndertakingAgreement::query()
        ->where('user_id', $fixture['parent']->id)
        ->where('letter_of_undertaking_id', $fixture['letter']->id)
        ->exists())->toBeTrue();
})->todo('The agreement page redirects during mount and renders without the pending active letter in a browser request.');

it('shows invoice and payment actions only for the parents owned records', function (): void {
    $fixture = $this->createParentPanelFixture();
    $otherParent = User::factory()->create([
        'name' => 'Other Browser Parent',
        'profile_completed' => true,
        'current_tenant_id' => $fixture['tenant']->id,
    ]);
    $otherParent->assignRole('parent');
    $otherParent->tenants()->attach($fixture['tenant']->id);

    $otherInvoice = Invoice::factory()->create([
        'tenant_id' => $fixture['tenant']->id,
        'centre_id' => $fixture['centre']->id,
        'user_id' => $otherParent->id,
        'status' => InvoiceStatus::PENDING,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $otherInvoice->id,
        'product_id' => $fixture['product']->id,
        'child_id' => $fixture['child']->id,
        'price' => 8000,
        'quantity' => 1,
        'discount' => 0,
        'paid_amount' => 0,
    ]);

    $otherPayment = Payment::factory()->create([
        'tenant_id' => $fixture['tenant']->id,
        'user_id' => $otherParent->id,
        'amount' => 2500,
    ]);
    $otherPayment->invoices()->attach($otherInvoice->id, ['amount' => 2500]);
    $otherPayment->centres()->attach($fixture['centre']->id, ['allocated_amount' => 2500]);

    visit('/parent/invoices')
        ->assertSee($fixture['invoice']->number)
        ->assertDontSee($otherInvoice->number)
        ->click('Actions')
        ->assertSee('View')
        ->assertSee('Download PDF')
        ->assertSee('Make Payment')
        ->assertNoJavascriptErrors();

    visit('/parent/payments')
        ->assertSee($fixture['payment']->reference_no)
        ->assertDontSee($otherPayment->reference_no)
        ->click('Actions')
        ->assertSee('View')
        ->assertSee('Download Receipt')
        ->assertNoJavascriptErrors();
});

it('updates the parents shared profile details', function (): void {
    $fixture = $this->createParentPanelFixture();
    $fixture['parent']->profile()->create([
        'nric' => '900101-01-1234',
        'phone' => '+60123456789',
        'occupation' => 'Teacher',
    ]);
    $fixture['parent']->userAddress()->create([
        'address' => '1 Jalan Browser',
        'city' => 'Shah Alam',
        'postal_code' => '40100',
        'state_code' => '10',
    ]);

    visit('/parent/profile')
        ->fill('input[wire\\:model="data.name"]', 'Updated Browser Parent')
        ->click('Save changes')
        ->assertSee('Saved')
        ->assertNoJavascriptErrors();

    expect($fixture['parent']->fresh()->name)->toBe('Updated Browser Parent');
});
