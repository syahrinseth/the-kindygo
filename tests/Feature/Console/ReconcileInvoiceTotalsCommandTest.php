<?php

use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tenant;
use App\Models\User;

function createReconciliationInvoice(Tenant $tenant, int $price, int $quantity, int $discount): Invoice
{
    $user = User::factory()->create();
    $centre = Centre::factory()->create(['tenant_id' => $tenant->id]);
    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'centre_id' => $centre->id,
        'user_id' => $user->id,
        'total_items' => 0,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'price' => $price,
        'quantity' => $quantity,
        'discount' => $discount,
    ]);

    $invoice->refresh();
    $invoice->updateQuietly([
        'total_items' => 0,
        'subtotal_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
    ]);

    return $invoice;
}

it('reconciles inconsistent totals across all tenants', function () {
    $firstInvoice = createReconciliationInvoice(Tenant::factory()->create(), 74000, 2, 500);
    $secondInvoice = createReconciliationInvoice(Tenant::factory()->create(), 20000, 1, 0);
    $firstInvoice->updateQuietly(['status' => InvoiceStatus::PAID]);

    $this->artisan('invoices:reconcile-totals')
        ->expectsOutput('Checked: 2')
        ->expectsOutput('Corrected: 2')
        ->expectsOutput('Unchanged: 0')
        ->assertSuccessful();

    expect($firstInvoice->fresh()->only(['total_items', 'subtotal_amount', 'discount_amount', 'total_amount']))
        ->toBe(['total_items' => 1, 'subtotal_amount' => 148000, 'discount_amount' => 1000, 'total_amount' => 147000])
        ->and($firstInvoice->fresh()->status)->toBe(InvoiceStatus::PAID)
        ->and($secondInvoice->fresh()->only(['total_items', 'subtotal_amount', 'discount_amount', 'total_amount']))
        ->toBe(['total_items' => 1, 'subtotal_amount' => 20000, 'discount_amount' => 0, 'total_amount' => 20000]);
});

it('honours tenant filtering and dry runs without updating records', function () {
    $firstTenant = Tenant::factory()->create();
    $firstInvoice = createReconciliationInvoice($firstTenant, 30000, 1, 0);
    $secondInvoice = createReconciliationInvoice(Tenant::factory()->create(), 40000, 1, 0);

    $this->artisan('invoices:reconcile-totals', ['--tenant-id' => $firstTenant->id, '--dry-run' => true])
        ->expectsOutput('Checked: 1')
        ->expectsOutput('Would correct: 1')
        ->expectsOutput('Unchanged: 0')
        ->assertSuccessful();

    expect($firstInvoice->fresh()->total_amount)->toBe(0)
        ->and($secondInvoice->fresh()->total_amount)->toBe(0);

    $this->artisan('invoices:reconcile-totals', ['--tenant-id' => $firstTenant->id])
        ->assertSuccessful();

    expect($firstInvoice->fresh()->total_amount)->toBe(30000)
        ->and($secondInvoice->fresh()->total_amount)->toBe(0);
});
