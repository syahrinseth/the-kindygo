<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Filament\Parent\Widgets\StatsOverviewWidget;
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

it('calculates outstanding data correctly', function () {
    // Create pending invoices
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000, // 100.00 in cents
    ]);

    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::OVERDUE,
        'total' => 15000, // 150.00 in cents
    ]);

    // Create a paid invoice (should not be included)
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PAID,
        'total' => 5000,
    ]);

    $widget = new StatsOverviewWidget;
    $outstandingData = $widget->getOutstandingData($this->user->id);

    expect($outstandingData['total'])->toBe(25000); // 100 + 150 = 250.00 in cents
    expect($outstandingData['count'])->toBe(2);
    expect($outstandingData['trend'])->toBeArray();
});

it('calculates overdue count correctly', function () {
    // Create overdue invoices
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::OVERDUE,
        'total' => 10000,
    ]);

    // Create pending invoice with past due date (should count as overdue)
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'due_at' => now()->subDays(5),
        'total' => 15000,
    ]);

    // Create pending invoice with future due date (should not count as overdue)
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'due_at' => now()->addDays(10),
        'total' => 5000,
    ]);

    $widget = new StatsOverviewWidget;
    $overdueCount = $widget->getOverdueCount($this->user->id);

    expect($overdueCount)->toBe(2);
});

it('calculates paid this month correctly', function () {
    // Create payments this month
    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 10000,
        'paid_at' => now(),
    ]);

    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 15000,
        'paid_at' => now()->subDays(5),
    ]);

    // Create payment last month (should not be included)
    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PAID,
        'amount' => 5000,
        'paid_at' => now()->subMonth(),
    ]);

    // Create pending payment (should not be included)
    Payment::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => PaymentStatus::PENDING,
        'amount' => 3000,
        'paid_at' => now(),
    ]);

    $widget = new StatsOverviewWidget;
    $paidThisMonth = $widget->getPaidThisMonth($this->user->id);

    expect($paidThisMonth['total'])->toBe(25000); // 100 + 150 = 250.00 in cents
    expect($paidThisMonth['count'])->toBe(2);
    expect($paidThisMonth['trend'])->toBeArray();
});

it('returns empty stats when no data exists', function () {
    $widget = new StatsOverviewWidget;

    $outstandingData = $widget->getOutstandingData($this->user->id);
    $overdueCount = $widget->getOverdueCount($this->user->id);
    $paidThisMonth = $widget->getPaidThisMonth($this->user->id);

    expect($outstandingData['total'])->toBe(0);
    expect($outstandingData['count'])->toBe(0);
    expect($overdueCount)->toBe(0);
    expect($paidThisMonth['total'])->toBe(0);
    expect($paidThisMonth['count'])->toBe(0);
});

it('only includes invoices from the correct user and tenant', function () {
    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create();

    // Create invoice for other user
    Invoice::factory()->create([
        'user_id' => $otherUser->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 10000,
    ]);

    // Create invoice for other tenant
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $otherTenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 15000,
    ]);

    // Create invoice for correct user and tenant
    Invoice::factory()->create([
        'user_id' => $this->user->id,
        'tenant_id' => $this->tenant->id,
        'status' => InvoiceStatus::PENDING,
        'total' => 5000,
    ]);

    $widget = new StatsOverviewWidget;
    $outstandingData = $widget->getOutstandingData($this->user->id);

    expect($outstandingData['total'])->toBe(5000);
    expect($outstandingData['count'])->toBe(1);
});
