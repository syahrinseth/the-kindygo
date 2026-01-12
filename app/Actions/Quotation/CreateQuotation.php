<?php

namespace App\Actions\Quotation;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Carbon\Carbon;

class CreateQuotation
{
    public function execute(
        int $tenantId,
        int $centreId,
        int $userId,
        ?int $childId,
        Carbon $date,
        ?Carbon $validUntil = null
    ): Quotation {
        // Default valid_until to 30 days from date
        if (! $validUntil) {
            $validUntil = $date->copy()->addDays(30);
        }

        return Quotation::create([
            'tenant_id' => $tenantId,
            'centre_id' => $centreId,
            'user_id' => $userId,
            'child_id' => $childId,
            'date' => $date,
            'valid_until' => $validUntil,
            'status' => QuotationStatus::DRAFT->value,
            'total_items' => 0,
            'total_amount' => 0,
            'total' => 0,
        ]);
    }
}
