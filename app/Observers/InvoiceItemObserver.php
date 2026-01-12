<?php

namespace App\Observers;

use App\Actions\Ledger\CreateInvoiceItemLedgerEntryAction;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Log;

class InvoiceItemObserver
{
    public function __construct(
        protected CreateInvoiceItemLedgerEntryAction $createLedgerEntry
    ) {}

    /**
     * Handle the InvoiceItem "created" event.
     * Create initial debit ledger entry when invoice item is created.
     */
    public function created(InvoiceItem $invoiceItem): void
    {
        try {
            // Ensure invoice relationship is loaded
            $invoiceItem->loadMissing(['invoice', 'product']);

            // Create initial ledger entry (debit - what customer owes)
            $this->createLedgerEntry->execute($invoiceItem);

            Log::info('Ledger debit entry created for invoice item', [
                'invoice_item_id' => $invoiceItem->id,
                'invoice_id' => $invoiceItem->invoice_id,
                'amount' => $invoiceItem->total,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create ledger entry for invoice item', [
                'invoice_item_id' => $invoiceItem->id,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the invoice item creation, just log the error
            // The ledger entry can be backfilled later
        }
    }
}
