<?php

use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChildEnrolmentInvoiceService;
use Carbon\Carbon;

beforeEach(function () {
    // Create test data
    $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
    $this->centre = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test Centre',
        'code' => 'TC',
    ]);
    $this->parent = User::factory()->create([
        'name' => 'Test Parent',
        'current_tenant_id' => $this->tenant->id,
    ]);
    $this->parent->tenants()->attach($this->tenant->id);
    $this->child = Child::factory()->create([
        'first_name' => 'Test',
        'last_name' => 'Child',
    ]);
    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Monthly Care',
    ]);

    // Create price for the product
    \App\Models\ProductPrice::factory()->create([
        'product_id' => $this->product->id,
        'price' => 30000, // 300.00 in cents
        'start_date' => Carbon::now()->subMonth(),
    ]);

    // Associate child with tenant
    $this->child->tenants()->attach($this->tenant->id);

    // Associate child with centre
    $this->child->centres()->attach($this->centre->id);

    // Associate parent with child
    $this->parent->children()->attach($this->child->id);

    // Set current tenant
    app()->instance('current_tenant', $this->tenant);
});

test('invoice generation groups by tenant user centre', function () {
    // Create enrolments for the same parent and centre
    $enrolment1 = ChildEnrolment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'next_bill_date' => Carbon::now()->subDay(), // Due for billing
        'billed_every' => 'monthly',
    ]);

    $enrolment2 = ChildEnrolment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'next_bill_date' => Carbon::now()->subDay(),
        'billed_every' => 'monthly',
    ]);

    $service = app(ChildEnrolmentInvoiceService::class);
    $enrolments = collect([$enrolment1, $enrolment2]);
    $generatedInvoices = $service->generateInvoicesForEnrolments($enrolments);

    // Should generate only 1 invoice (grouped by tenant, user, centre)
    expect($generatedInvoices)->toHaveCount(1);

    $invoice = $generatedInvoices[0];
    expect($invoice->tenant_id)->toBe($this->tenant->id);
    expect($invoice->user_id)->toBe($this->parent->id);
    expect($invoice->centre_id)->toBe($this->centre->id);

    // Should have 2 invoice items (one for each enrolment)
    expect($invoice->items()->count())->toBe(2);

    // Each item should be linked to an enrolment
    foreach ($invoice->items as $item) {
        expect($item->child_enrolment_id)->not->toBeNull();
        expect($item->child_id)->not->toBeNull();
        expect($item->period_start)->not->toBeNull();
        expect($item->period_end)->not->toBeNull();
    }
});

test('invoice item tracks child and enrolment', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'next_bill_date' => Carbon::now()->subDay(),
        'billed_every' => 'monthly',
    ]);

    $service = app(ChildEnrolmentInvoiceService::class);
    $invoices = $service->generateInvoicesForEnrolment($enrolment);

    expect($invoices)->toHaveCount(1);
    $invoice = $invoices[0];

    $item = $invoice->items()->first();
    expect($item->child_enrolment_id)->toBe($enrolment->id);
    expect($item->child_id)->toBe($this->child->id);

    // Test relationships
    expect($item->childEnrolment->id)->toBe($enrolment->id);
    expect($item->childEnrolment->child->name)->toBe($this->child->name);
});

test('invoice helper methods', function () {
    $enrolment = ChildEnrolment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'child_id' => $this->child->id,
        'product_id' => $this->product->id,
        'next_bill_date' => Carbon::now()->subDay(),
        'billed_every' => 'monthly',
    ]);

    $service = app(ChildEnrolmentInvoiceService::class);
    $invoices = $service->generateInvoicesForEnrolment($enrolment);

    $invoice = $invoices[0];

    // Test helper methods
    expect($invoice->children()->count())->toBe(1);
    expect($invoice->childEnrolments()->count())->toBe(1);
    expect($invoice->children()->first()->id)->toBe($this->child->id);
    expect($invoice->childEnrolments()->first()->id)->toBe($enrolment->id);
});

test('child name accessor', function () {
    expect($this->child->name)->toBe('Test Child');
    expect($this->child->full_name)->toBe('Test Child');
});

test('invoice numbers are sequential for same tenant centre and year', function () {
    // Create multiple invoices rapidly for the same tenant/centre/year
    $invoices = [];
    for ($i = 0; $i < 10; $i++) {
        $invoices[] = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'date' => Carbon::now(), // Same year
        ]);
    }

    // Extract sequential numbers from invoice numbers
    $sequentialNumbers = collect($invoices)->map(function ($invoice) {
        preg_match('/[A-Z0-9]+\/\d{4}\/(\d+)$/', $invoice->number, $matches);

        return (int) $matches[1];
    })->sort()->values();

    // Assert numbers are sequential: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10
    $expectedNumbers = range(1, 10);
    expect($sequentialNumbers->toArray())->toBe($expectedNumbers);

    // Assert all invoice numbers are unique
    $uniqueNumbers = collect($invoices)->pluck('number')->unique();
    expect($uniqueNumbers)->toHaveCount(10);
});

test('invoice numbers reset per year', function () {
    // Create invoice in 2025
    $invoice2025 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'date' => Carbon::create(2025, 1, 1),
    ]);

    // Create invoice in 2026
    $invoice2026 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'date' => Carbon::create(2026, 1, 1),
    ]);

    // Both should have sequential number 0001
    expect($invoice2025->number)->toContain('/2025/0001');
    expect($invoice2026->number)->toContain('/2026/0001');
});

test('invoice numbers are scoped by centre', function () {
    // Create another centre
    $centre2 = Centre::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Second Centre',
        'code' => 'SC',
    ]);

    // Create invoices for first centre
    $invoice1Centre1 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $this->centre->id,
        'date' => Carbon::now(),
    ]);

    // Create invoice for second centre
    $invoice1Centre2 = Invoice::factory()->create([
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre2->id,
        'date' => Carbon::now(),
    ]);

    // Both should start at 0001 (different centres)
    expect($invoice1Centre1->number)->toContain('/0001');
    expect($invoice1Centre2->number)->toContain('/0001');

    // But should have different centre codes
    expect($invoice1Centre1->number)->toContain('KGTC/');
    expect($invoice1Centre2->number)->toContain('KGSC/');
});
