<?php

namespace App\Actions\Quotation;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;

class ConvertQuotationToInvoice
{
    public function __construct(
        protected UpdateQuotationTotals $updateQuotationTotals,
    ) {}

    public function execute(Quotation $quotation, array $selectedItemIds): Invoice
    {
        // Create invoice with quotation attributes
        $invoice = Invoice::create([
            'tenant_id' => $quotation->tenant_id,
            'centre_id' => $quotation->centre_id,
            'user_id' => $quotation->user_id,
            'date' => now(),
            'due_at' => now()->addDays(7),
            'status' => InvoiceStatus::PENDING->value,
            'total_items' => 0,
            'total_discounts' => 0,
            'total_amount' => 0,
            'total' => 0,
        ]);

        // Get selected quotation items
        $selectedItems = $quotation->quotationItems()
            ->whereIn('id', $selectedItemIds)
            ->get();

        // Create invoice items from selected quotation items
        foreach ($selectedItems as $quotationItem) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $quotationItem->product_id,
                'child_id' => $quotationItem->child_id,
                'child_enrolment_id' => $quotationItem->child_enrolment_id,
                'name' => $quotationItem->name,
                'description' => $quotationItem->description,
                'price' => $quotationItem->price,
                'quantity' => $quotationItem->quantity,
                'discount' => $quotationItem->discount,
                'total' => $quotationItem->total,
                'type' => $quotationItem->type,
                'effective_date' => $quotationItem->effective_date,
                'period_start' => $quotationItem->period_start,
                'period_end' => $quotationItem->period_end,
            ]);
        }

        // Update invoice totals (will be auto-calculated via boot hooks, but ensure it's done)
        $invoice->calculateAndUpdateTotals();

        // Mark quotation as converted
        $quotation->update([
            'status' => QuotationStatus::CONVERTED->value,
            'converted_invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }
}
