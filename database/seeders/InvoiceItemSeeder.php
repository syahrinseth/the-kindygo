<?php

namespace Database\Seeders;

use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Child;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $invoices = Invoice::all();
        $products = Product::all();
        $children = Child::all();
        
        if ($invoices->isEmpty()) {
            $this->command->info('No invoices found. Please create invoices first.');
            return;
        }

        if ($products->isEmpty()) {
            $this->command->info('No products found. Please run ProductSeeder first.');
            return;
        }

        foreach ($invoices as $invoice) {
            // Get products for the same tenant
            $tenantProducts = $products->where('tenant_id', $invoice->tenant_id);
            
            // Get children for the same tenant (if any)
            $tenantChildren = $children->filter(function ($child) use ($invoice) {
                return $child->tenants->contains('id', $invoice->tenant_id);
            });

            // Create 1-4 invoice items per invoice
            $itemCount = rand(1, 4);
            
            for ($i = 0; $i < $itemCount; $i++) {
                $product = $tenantProducts->random();
                $child = $tenantChildren->isNotEmpty() ? $tenantChildren->random() : null;
                
                InvoiceItem::factory()->create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'child_id' => $child?->id,
                ]);
            }
        }

        $this->command->info('Invoice items seeded successfully!');
    }
}
