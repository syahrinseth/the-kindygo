<?php

namespace App\Actions\Invoice;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;

class AddEnrolmentItemsToInvoice
{
    public function __construct(
        protected AddProductItemsToInvoice $addProductItemsToInvoice,
    ) {}

    public function execute(Invoice $invoice, ChildEnrolment $enrolment): void
    {
        // Add main product items
        $this->addProductItemsToInvoice->execute(
            $invoice,
            $enrolment,
            $enrolment->product,
            $enrolment->billed_every,
            $enrolment->date_start,
            $enrolment->date_end
        );

        // Add additional product items
        foreach ($enrolment->additional_products ?? [] as $additionalProduct) {
            if (! isset($additionalProduct['product_id'])) {
                continue;
            }

            $product = Product::find($additionalProduct['product_id']);
            if (! $product) {
                continue;
            }

            $this->addProductItemsToInvoice->execute(
                $invoice,
                $enrolment,
                $product,
                ChildEnrolmentBilledEvery::from($additionalProduct['billed_every']),
                Carbon::parse($additionalProduct['date_start']),
                isset($additionalProduct['date_end']) ? Carbon::parse($additionalProduct['date_end']) : null,
                $additionalProduct['notes'] ?? null
            );
        }
    }
}
