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
     * @return array{total_items: int, total_amount: int, total_discounts: int, total: int}
     */
    public function calculate(Invoice $invoice): array
    {
        $totals = $invoice->invoiceItems()
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(discount * quantity), 0) as total_discounts')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'total_items' => (int) $totals->total_items,
            'total_amount' => (int) $totals->total_amount,
            'total_discounts' => (int) $totals->total_discounts,
            'total' => (int) $totals->total,
        ];
    }
}
