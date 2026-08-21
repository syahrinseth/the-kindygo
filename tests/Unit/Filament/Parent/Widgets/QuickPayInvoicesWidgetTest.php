<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Filament\Parent\Widgets\QuickPayInvoicesWidget;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    $this->user->current_tenant_id = $this->tenant->id;
    $this->user->save();

    test()->actingAs($this->user);
});

it('getInvoices returns unpaid invoices', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create unpaid invoices
    $pending = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $overdue = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::OVERDUE,
        'total_amount' => 15000,
        'due_at' => now()->subDays(3),
    ]);

    $partiallyPaid = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PARTIALLY_PAID,
        'total_amount' => 20000,
        'due_at' => now()->addDays(10),
    ]);

    // Create a paid invoice (should not be included)
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PAID,
        'total_amount' => 5000,
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toHaveCount(3);
    expect($invoices->pluck('id'))->toContain($pending->id, $overdue->id, $partiallyPaid->id);
});

it('getInvoices filters by remaining balance > 0', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create invoice with remaining balance
    $invoiceWithBalance = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PARTIALLY_PAID,
        'total_amount' => 10000,
    ]);

    // Create invoice that is fully paid (zero remaining balance)
    $fullyPaidInvoice = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PARTIALLY_PAID,
        'total_amount' => 10000,
    ]);

    // Record payment that covers the full invoice
    $payment = Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'amount' => 10000,
        'status' => PaymentStatus::PAID,
    ]);
    $fullyPaidInvoice->payments()->attach($payment->id, ['amount' => 10000]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toHaveCount(1);
    expect($invoices->first()->id)->toBe($invoiceWithBalance->id);
});

it('getInvoices limits to 10 invoices', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create 12 unpaid invoices
    Invoice::factory(12)->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toHaveCount(10);
});

it('getInvoices sorts by due date ascending', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
        'due_at' => now()->addDays(10),
    ]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $invoice3 = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
        'due_at' => now()->addDays(15),
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices->pluck('id')->toArray())->toBe([
        $invoice2->id, // 5 days
        $invoice1->id, // 10 days
        $invoice3->id, // 15 days
    ]);
});

it('getInvoices excludes invoices with zero total', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create invoice with zero total
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 0,
    ]);

    // Create invoice with positive total
    $invoiceWithTotal = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toHaveCount(1);
    expect($invoices->first()->id)->toBe($invoiceWithTotal->id);
});

it('getInvoices only returns invoices for authenticated user', function () {
    $otherUser = User::factory()->create();
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create invoice for another user
    Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
    ]);

    // Create invoice for current user
    $userInvoice = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toHaveCount(1);
    expect($invoices->first()->id)->toBe($userInvoice->id);
});

it('getInvoices returns empty collection when no user', function () {
    // Logout user
    auth()->logout();

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toBeEmpty();
});

it('getInvoices returns empty collection when no current tenant', function () {
    // Remove current tenant
    $this->user->current_tenant_id = null;
    $this->user->save();

    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total_amount' => 10000,
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toBeEmpty();
});

it('getInvoices returns empty collection when no unpaid invoices', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create only paid invoices
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PAID,
        'total_amount' => 10000,
    ]);

    $widget = new QuickPayInvoicesWidget;
    $invoices = $widget->getInvoices();

    expect($invoices)->toBeEmpty();
});
