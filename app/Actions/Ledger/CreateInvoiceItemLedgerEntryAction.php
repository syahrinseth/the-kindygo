<?php

namespace App\Actions\Ledger;

use App\Models\InvoiceItem;
use App\Models\InvoiceItemsLedger;

class CreateInvoiceItemLedgerEntryAction
{
    /**
     * Create initial debit ledger entry when an invoice item is created.
     * This records what the customer owes for this specific item.
     */
    public function execute(InvoiceItem $invoiceItem): InvoiceItemsLedger
    {
        $invoice = $invoiceItem->invoice;
        $product = $invoiceItem->product;

        // Get priority from product if available, otherwise default to MEDIUM (2)
        $priority = $product?->priority?->value ?? 2;

        return InvoiceItemsLedger::create([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => $invoice->user_id, // Parent/payer
            'centre_id' => $invoice->centre_id,
            'ledger_type' => 'invoice_item',
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoiceItem->id,
            'payment_id' => null, // No payment yet
            'child_id' => $invoiceItem->child_id,
            'product_id' => $invoiceItem->product_id,
            'description' => $invoiceItem->description ?? $invoiceItem->name,
            'debit_amount' => $invoiceItem->total, // What customer owes
            'credit_amount' => 0, // No payment yet
            'balance_amount' => $invoiceItem->total, // Full balance outstanding
            'paid' => false,
            'priority' => $priority,
            'reference_data' => [
                'invoice_number' => $invoice->number,
                'item_type' => 'initial_invoice_item',
                'created_via' => 'invoice_item_created',
            ],
            'recorded_at' => $invoice->date ?? now(),
        ]);
    }
}
