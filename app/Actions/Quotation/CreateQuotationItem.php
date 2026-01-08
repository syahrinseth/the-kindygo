<?php

namespace App\Actions\Quotation;

use App\Enums\InvoiceItemType;
use App\Models\ChildEnrolment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Carbon\Carbon;

class CreateQuotationItem
{
    public function execute(
        Quotation $quotation,
        Product $product,
        ?ChildEnrolment $enrolment,
        Carbon $periodStart,
        ?Carbon $periodEnd,
        ?string $notes = null
    ): QuotationItem {
        $description = $this->buildDescription($product, $periodStart, $periodEnd, $notes);
        $priceInCents = $this->getPriceForProduct($product, $quotation->centre_id);

        return QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'child_id' => $enrolment?->child_id ?? $quotation->child_id,
            'child_enrolment_id' => $enrolment?->id,
            'type' => InvoiceItemType::PRODUCT,
            'name' => $product->name,
            'description' => $description,
            'quantity' => 1,
            'price' => $priceInCents,
            'total' => $priceInCents,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
    }

    private function buildDescription(
        Product $product,
        Carbon $periodStart,
        ?Carbon $periodEnd,
        ?string $notes
    ): string {
        $description = $product->name;

        if ($periodEnd && ! $periodStart->isSameDay($periodEnd)) {
            $description .= " ({$periodStart->format('M j')} - {$periodEnd->format('M j, Y')})";
        } else {
            $description .= " ({$periodStart->format('M j, Y')})";
        }

        if ($notes) {
            $description .= " - {$notes}";
        }

        return $description;
    }

    private function getPriceForProduct(Product $product, int $centreId): int
    {
        $productPrice = $product->currentPriceForCentre($centreId)
            ?? $product->currentPrice;

        return $productPrice ? (int) $productPrice->price : 0;
    }
}
