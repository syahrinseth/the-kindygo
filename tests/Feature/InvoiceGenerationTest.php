<?php

namespace Tests\Feature;

use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ChildEnrolmentInvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $centre;
    protected $parent;
    protected $child;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
        $this->centre = Centre::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Centre',
            'code' => 'TC'
        ]);
        $this->parent = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Parent'
        ]);
        $this->child = Child::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Child'
        ]);
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Monthly Care',
            'price' => 300.00,
            'is_main' => true
        ]);

        // Associate child with tenant
        $this->child->tenants()->attach($this->tenant->id);

        // Associate child with centre
        $this->child->centres()->attach($this->centre->id);

        // Associate parent with child
        $this->parent->children()->attach($this->child->id);

        // Set current tenant
        app()->instance('current_tenant', $this->tenant);
    }

    public function test_invoice_generation_groups_by_tenant_user_centre()
    {
        // Create enrolments for the same parent and centre
        $enrolment1 = ChildEnrolment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'user_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'product_id' => $this->product->id,
            'next_billing_at' => Carbon::now()->subDay(), // Due for billing
            'billed_every' => 'monthly'
        ]);

        $enrolment2 = ChildEnrolment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'user_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'product_id' => $this->product->id,
            'next_billing_at' => Carbon::now()->subDay(),
            'billed_every' => 'monthly'
        ]);

        $service = new ChildEnrolmentInvoiceService();
        $enrolments = collect([$enrolment1, $enrolment2]);
        $generatedInvoices = $service->generateInvoicesForEnrolments($enrolments);

        // Should generate only 1 invoice (grouped by tenant, user, centre)
        $this->assertCount(1, $generatedInvoices);

        $invoice = $generatedInvoices[0];
        $this->assertEquals($this->tenant->id, $invoice->tenant_id);
        $this->assertEquals($this->parent->id, $invoice->user_id);
        $this->assertEquals($this->centre->id, $invoice->centre_id);

        // Should have 2 invoice items (one for each enrolment)
        $this->assertEquals(2, $invoice->items()->count());

        // Each item should be linked to an enrolment
        foreach ($invoice->items as $item) {
            $this->assertNotNull($item->child_enrolment_id);
            $this->assertNotNull($item->child_id);
            $this->assertNotNull($item->period_start);
            $this->assertNotNull($item->period_end);
        }
    }

    public function test_invoice_item_tracks_child_and_enrolment()
    {
        $enrolment = ChildEnrolment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'user_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'product_id' => $this->product->id,
            'next_billing_at' => Carbon::now()->subDay(),
            'billed_every' => 'monthly'
        ]);

        $service = new ChildEnrolmentInvoiceService();
        $invoices = $service->generateInvoicesForEnrolment($enrolment);

        $this->assertCount(1, $invoices);
        $invoice = $invoices[0];

        $item = $invoice->items()->first();
        $this->assertEquals($enrolment->id, $item->child_enrolment_id);
        $this->assertEquals($this->child->id, $item->child_id);

        // Test relationships
        $this->assertEquals($enrolment->id, $item->childEnrolment->id);
        $this->assertEquals($this->child->name, $item->childEnrolment->child->name);
    }

    public function test_invoice_helper_methods()
    {
        $enrolment = ChildEnrolment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'centre_id' => $this->centre->id,
            'user_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'product_id' => $this->product->id,
            'next_billing_at' => Carbon::now()->subDay(),
            'billed_every' => 'monthly'
        ]);

        $service = new ChildEnrolmentInvoiceService();
        $invoices = $service->generateInvoicesForEnrolment($enrolment);

        $invoice = $invoices[0];

        // Test helper methods
        $this->assertEquals(1, $invoice->children()->count());
        $this->assertEquals(1, $invoice->childEnrolments()->count());
        $this->assertEquals($this->child->id, $invoice->children()->first()->id);
        $this->assertEquals($enrolment->id, $invoice->childEnrolments()->first()->id);
    }

    public function test_child_name_accessor()
    {
        $this->assertEquals('Test Child', $this->child->name);
        $this->assertEquals('Test Child', $this->child->full_name);
    }
}
