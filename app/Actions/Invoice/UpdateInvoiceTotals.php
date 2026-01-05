<?php

namespace App\Actions\Invoice;

use App\Models\Invoice;

class UpdateInvoiceTotals
{
    public function execute(Invoice $invoice): void
    {
        $totalItems = $invoice->invoiceItems()->count();
        $totalAmount = $invoice->invoiceItems()->sum('total');

        $invoice->update([
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'total' => $totalAmount,
        ]);
    }
}
