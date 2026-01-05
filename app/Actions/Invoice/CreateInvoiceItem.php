<?php

namespace App\Actions\Invoice;

use App\Enums\InvoiceItemType;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Carbon\Carbon;

class CreateInvoiceItem
{
    public function execute(
        Invoice $invoice,
        ChildEnrolment $enrolment,
        Product $product,
        Carbon $periodStart,
        ?Carbon $periodEnd,
        ?string $notes = null
    ): InvoiceItem {
        $description = $this->buildDescription($product, $periodStart, $periodEnd, $notes);
        $priceInCents = $this->getPriceForProduct($product, $enrolment);

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'child_id' => $enrolment->child_id,
            'child_enrolment_id' => $enrolment->id,
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

    private function getPriceForProduct(Product $product, ChildEnrolment $enrolment): int
    {
        $productPrice = $product->currentPriceForCentre($enrolment->centre_id)
            ?? $product->currentPrice;

        return $productPrice ? (int) $productPrice->price : 0;
    }
}
