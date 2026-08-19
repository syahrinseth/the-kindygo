<?php

namespace Tests\Traits;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Enums\InvoiceStatus;
use App\Models\Campus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;
use App\Models\LetterOfUndertaking;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait CreatesPanelBrowserFixtures
{
    /**
     * @return array<string, mixed>
     */
    protected function createAdminPanelFixture(string $role = 'admin'): array
    {
        $this->ensurePanelRolesExist();

        $admin = User::factory()->create([
            'name' => 'Browser Admin',
            'profile_completed' => true,
        ]);
        $tenant = Tenant::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Browser Organisation',
            'slug' => 'browser-organisation',
        ]);
        $admin->assignRole($role);
        $admin->tenants()->attach($tenant->id);
        $admin->update(['current_tenant_id' => $tenant->id]);

        $parent = User::factory()->create([
            'name' => 'Browser Parent',
            'profile_completed' => true,
            'current_tenant_id' => $tenant->id,
        ]);
        $parent->assignRole('parent');
        $parent->tenants()->attach($tenant->id);

        $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
        $centre = Centre::factory()->create([
            'tenant_id' => $tenant->id,
            'campus_id' => $campus->id,
            'name' => 'Browser Centre',
        ]);
        $child = Child::factory()->create([
            'first_name' => 'Aisyah',
            'last_name' => 'Browser',
        ]);
        $child->addToTenant($tenant);
        $parent->children()->attach($child->id, ['relationship_type' => 'Parent']);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Browser Tuition Fee',
            'status' => 'active',
        ]);
        $product->centres()->attach($centre->id);
        $productPrice = ProductPrice::factory()->create([
            'product_id' => $product->id,
        ]);

        $enrolment = ChildEnrolment::factory()->create([
            'tenant_id' => $tenant->id,
            'centre_id' => $centre->id,
            'child_id' => $child->id,
            'product_id' => $product->id,
            'status' => ChildEnrolmentStatus::ACTIVE,
            'type' => ChildEnrolmentType::FULL_TIME,
            'billed_every' => ChildEnrolmentBilledEvery::MONTHLY,
        ]);

        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'centre_id' => $centre->id,
            'user_id' => $parent->id,
            'status' => InvoiceStatus::PENDING,
        ]);
        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'child_id' => $child->id,
            'child_enrolment_id' => $enrolment->id,
            'price' => 12500,
            'quantity' => 1,
            'discount' => 0,
            'paid_amount' => 0,
        ]);
        $invoice->refresh()->update(['status' => InvoiceStatus::PENDING]);

        $payment = Payment::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $parent->id,
            'amount' => 5000,
        ]);
        $payment->invoices()->attach($invoice->id, ['amount' => 5000]);
        $payment->centres()->attach($centre->id, ['allocated_amount' => 5000]);

        $ledger = InvoiceItemsLedger::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $parent->id,
            'centre_id' => $centre->id,
            'ledger_type' => 'invoice_item',
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoiceItem->id,
            'child_id' => $child->id,
            'product_id' => $product->id,
            'description' => 'Browser ledger entry',
            'debit_amount' => 12500,
            'credit_amount' => 0,
            'balance_amount' => 12500,
            'paid' => false,
            'recorded_at' => now(),
        ]);

        $quotation = Quotation::factory()->pending()->create([
            'tenant_id' => $tenant->id,
            'centre_id' => $centre->id,
            'user_id' => $parent->id,
            'child_id' => $child->id,
        ]);
        $quotationItem = QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
        ]);

        $letter = LetterOfUndertaking::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $admin->id,
            'title' => 'Browser Agreement',
        ]);

        $this->actingAs($admin);

        return compact(
            'admin',
            'tenant',
            'parent',
            'campus',
            'centre',
            'child',
            'product',
            'productPrice',
            'enrolment',
            'invoice',
            'invoiceItem',
            'payment',
            'ledger',
            'quotation',
            'quotationItem',
            'letter',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function createParentPanelFixture(): array
    {
        $fixture = $this->createAdminPanelFixture();
        $this->actingAs($fixture['parent']);

        return $fixture;
    }

    protected function ensurePanelRolesExist(): void
    {
        foreach (['super-admin', 'admin', 'principal', 'teacher', 'parent'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
