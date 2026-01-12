<?php

use App\Enums\InvoiceStatus;
use App\Filament\Parent\Pages\MakePayment;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('identifies multi-centre selections correctly', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre1->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000, // 100.00 in cents
    ]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre2->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 15000, // 150.00 in cents
    ]);

    test()->actingAs($user);

    $page = new MakePayment;
    $page->mount();

    // Select both invoices
    $page->selectedInvoices[$invoice1->id] = true;
    $page->selectedInvoices[$invoice2->id] = true;
    $page->selectedAmounts[$invoice1->id] = 100.00;
    $page->selectedAmounts[$invoice2->id] = 150.00;

    expect($page->isMultiCentreSelection())->toBeTrue();
    expect($page->getSelectedCentres())->toHaveCount(2);
});

it('calculates centre totals correctly', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Centre A']);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Centre B']);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre1->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000, // 100.00 in cents
    ]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre1->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 5000, // 50.00 in cents
    ]);

    $invoice3 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre2->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 20000, // 200.00 in cents
    ]);

    test()->actingAs($user);

    $page = new MakePayment;
    $page->mount();

    // Select all invoices with specific amounts
    $page->selectedInvoices[$invoice1->id] = true;
    $page->selectedInvoices[$invoice2->id] = true;
    $page->selectedInvoices[$invoice3->id] = true;
    $page->selectedAmounts[$invoice1->id] = 100.00;
    $page->selectedAmounts[$invoice2->id] = 50.00;
    $page->selectedAmounts[$invoice3->id] = 200.00;

    $centreTotals = $page->getCentreTotals();

    expect($centreTotals)->toHaveCount(2);

    $centre1Total = collect($centreTotals)->firstWhere('centre_id', $centre1->id);
    expect($centre1Total['total'])->toBe(150.00);
    expect($centre1Total['centre_name'])->toBe('Centre A');

    $centre2Total = collect($centreTotals)->firstWhere('centre_id', $centre2->id);
    expect($centre2Total['total'])->toBe(200.00);
    expect($centre2Total['centre_name'])->toBe('Centre B');
});

it('groups invoices by centre correctly', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $user->current_tenant_id = $tenant->id;
    $user->save();

    $centre1 = Centre::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Centre X']);
    $centre2 = Centre::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Centre Y']);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre1->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
    ]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre2->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
    ]);

    $invoice3 = Invoice::factory()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'centre_id' => $centre1->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
    ]);

    test()->actingAs($user);

    $page = new MakePayment;
    $page->mount();

    $grouped = $page->getInvoicesByCentre();

    expect($grouped)->toHaveCount(2);

    $centre1Group = collect($grouped)->firstWhere('centre_id', $centre1->id);
    expect($centre1Group['invoices'])->toHaveCount(2);
    expect($centre1Group['centre_name'])->toBe('Centre X');

    $centre2Group = collect($grouped)->firstWhere('centre_id', $centre2->id);
    expect($centre2Group['invoices'])->toHaveCount(1);
    expect($centre2Group['centre_name'])->toBe('Centre Y');
});
