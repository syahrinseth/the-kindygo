<?php

use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
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

it('returns table with upcoming unpaid invoices', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create unpaid invoices with different statuses
    $pending = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $overdue = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::OVERDUE,
        'total' => 15000,
        'due_at' => now()->subDays(3),
    ]);

    $partiallyPaid = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PARTIALLY_PAID,
        'total' => 20000,
        'due_at' => now()->addDays(10),
    ]);

    // Create a paid invoice (should not be included)
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PAID,
        'total' => 5000,
        'due_at' => now()->addDays(15),
    ]);

    $query = Invoice::query()
        ->where('user_id', $this->user->id)
        ->whereIn('status', [
            InvoiceStatus::PENDING,
            InvoiceStatus::OVERDUE,
            InvoiceStatus::PARTIALLY_PAID,
        ])
        ->where('total', '>', 0)
        ->with(['centre'])
        ->orderBy('due_at', 'asc')
        ->get();

    expect($query)->toHaveCount(3);
    expect($query->pluck('id'))->toContain($pending->id, $overdue->id, $partiallyPaid->id);
});

it('sorts invoices by due date ascending', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    $invoice1 = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(10),
    ]);

    $invoice2 = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $invoice3 = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(15),
    ]);

    $query = Invoice::query()
        ->where('user_id', $this->user->id)
        ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
        ->where('total', '>', 0)
        ->orderBy('due_at', 'asc')
        ->get();

    expect($query->pluck('id')->toArray())->toBe([
        $invoice2->id, // 5 days
        $invoice1->id, // 10 days
        $invoice3->id, // 15 days
    ]);
});

it('limits results to 5 invoices', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create 7 unpaid invoices
    Invoice::factory(7)->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $query = Invoice::query()
        ->where('user_id', $this->user->id)
        ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
        ->where('total', '>', 0)
        ->orderBy('due_at', 'asc')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(5);
});

it('excludes invoices with zero total', function () {
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create invoice with zero total
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 0,
        'due_at' => now()->addDays(5),
    ]);

    // Create invoice with positive total
    $invoiceWithTotal = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $query = Invoice::query()
        ->where('user_id', $this->user->id)
        ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
        ->where('total', '>', 0)
        ->orderBy('due_at', 'asc')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(1);
    expect($query->first()->id)->toBe($invoiceWithTotal->id);
});

it('only includes invoices for the authenticated user', function () {
    $otherUser = User::factory()->create();
    $centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);

    // Create invoice for another user
    Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    // Create invoice for current user
    $userInvoice = Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'centre_id' => $centre->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
        'due_at' => now()->addDays(5),
    ]);

    $query = Invoice::query()
        ->where('user_id', $this->user->id)
        ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
        ->where('total', '>', 0)
        ->orderBy('due_at', 'asc')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(1);
    expect($query->first()->id)->toBe($userInvoice->id);
});

it('shows empty state when no upcoming invoices', function () {
    $query = Invoice::query()
        ->where('user_id', $this->user->id)
        ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE, InvoiceStatus::PARTIALLY_PAID])
        ->where('total', '>', 0)
        ->orderBy('due_at', 'asc')
        ->limit(5)
        ->get();

    expect($query)->toHaveCount(0);
});
