<?php

namespace App\Actions\Quotation;

use App\Models\Quotation;

class UpdateQuotationTotals
{
    public function execute(Quotation $quotation): void
    {
        $totalItems = $quotation->quotationItems()->count();
        $totalAmount = $quotation->quotationItems()->sum('total');

        $quotation->update([
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'total' => $totalAmount,
        ]);
    }
}
