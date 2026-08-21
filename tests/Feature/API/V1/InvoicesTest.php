<?php

use App\Constants\TokenAbility;
use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->centre = Centre::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create([
        'current_tenant_id' => $this->tenant->id,
        'email_verified_at' => now(),
    ]);
    $this->tenant->users()->attach($this->user->id);
});

describe('GET /api/v1/invoices', function () {
    it('returns list of invoices for authenticated user', function () {
        Invoice::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 3,
                ],
            ]);
    });

    it('filters invoices by status', function () {
        Invoice::factory()->pending()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);
        Invoice::factory()->paid()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/invoices?status=pending');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 1,
                ],
            ]);
    });

    it('only returns invoices belonging to authenticated user', function () {
        Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);
        // Create invoice for another user
        $otherUser = User::factory()->create();
        Invoice::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 1,
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/v1/invoices');

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/invoices/{invoice}', function () {
    it('returns invoice details', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'total_items' => 2,
            'subtotal_amount' => 15000,
            'discount_amount' => 1000,
            'total_amount' => 14000,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'invoice_number',
                    'total_items',
                    'subtotal_amount',
                    'subtotal_amount_formatted',
                    'discount_amount',
                    'discount_amount_formatted',
                    'total_amount',
                    'total_amount_formatted',
                    'subtotal',
                    'subtotal_formatted',
                    'tax_amount',
                    'tax_amount_formatted',
                    'total',
                    'total_formatted',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->number,
                    'total_items' => 2,
                    'subtotal_amount' => 15000,
                    'subtotal_amount_formatted' => 'RM 150.00',
                    'discount_amount' => 1000,
                    'discount_amount_formatted' => 'RM 10.00',
                    'total_amount' => 14000,
                    'total_amount_formatted' => 'RM 140.00',
                    'subtotal' => 15000,
                    'subtotal_formatted' => 'RM 150.00',
                    'tax_amount' => null,
                    'tax_amount_formatted' => null,
                    'total' => 14000,
                    'total_formatted' => 'RM 140.00',
                ],
            ]);
    });

    it('reports an unpaid past-due invoice as overdue', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'status' => InvoiceStatus::PENDING,
            'due_at' => now()->subDay(),
            'total_amount' => 14000,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonPath('data.amount_due', 14000);
    });

    it('calculates paid and due amounts from paid allocations', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'total_amount' => 14000,
        ]);
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'amount' => 5000,
        ]);
        $payment->invoices()->attach($invoice->id, ['amount' => 5000]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.amount_paid', 5000)
            ->assertJsonPath('data.amount_due', 9000);
    });

    it('returns 404 for non-existent invoice', function () {
        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson('/api/v1/invoices/99999');

        $response->assertNotFound();
    });

    it('returns 404 for invoice not belonging to user', function () {
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertUnauthorized();
    });
});

describe('GET /api/v1/invoices/{invoice}/pdf', function () {
    it('returns 404 when PDF not available', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");

        // PDF file doesn't exist, so should return 404
        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'Invoice PDF not available.',
            ]);
    });

    it('returns 404 for invoice not belonging to user', function () {
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        Sanctum::actingAs($this->user, TokenAbility::parentAbilities());

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertNotFound();
    });

    it('requires authentication', function () {
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
        ]);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertUnauthorized();
    });
});
