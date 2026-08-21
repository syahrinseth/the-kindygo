<?php

namespace App\Actions\Invoice;

use App\Models\Invoice;

class UpdateInvoiceTotals
{
    public function execute(Invoice $invoice): void
    {
        $totals = $this->calculate($invoice);

        $invoice->update($totals);
    }

    /**
     * @return array{total_items: int, subtotal_amount: int, discount_amount: int, total_amount: int}
     */
    public function calculate(Invoice $invoice): array
    {
        $totals = $invoice->invoiceItems()
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as subtotal_amount')
            ->selectRaw('COALESCE(SUM(discount * quantity), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(total), 0) as total_amount')
            ->first();

        return [
            'total_items' => (int) $totals->total_items,
            'subtotal_amount' => (int) $totals->subtotal_amount,
            'discount_amount' => (int) $totals->discount_amount,
            'total_amount' => (int) $totals->total_amount,
        ];
    }
}
