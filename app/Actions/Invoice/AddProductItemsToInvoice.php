<?php

namespace App\Actions\Invoice;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;

class AddProductItemsToInvoice
{
    public function __construct(
        protected CreateInvoiceItem $createInvoiceItem,
        protected CreateRecurringInvoiceItems $createRecurringInvoiceItems,
    ) {}

    public function execute(
        Invoice $invoice,
        ChildEnrolment $enrolment,
        Product $product,
        ChildEnrolmentBilledEvery $billedEvery,
        Carbon $dateStart,
        ?Carbon $dateEnd,
        ?string $notes = null
    ): void {
        if ($billedEvery === ChildEnrolmentBilledEvery::ONE_TIME) {
            $this->createInvoiceItem->execute(
                $invoice,
                $enrolment,
                $product,
                $dateStart,
                $dateEnd,
                $notes
            );
        } else {
            $this->createRecurringInvoiceItems->execute(
                $invoice,
                $enrolment,
                $product,
                $billedEvery,
                $dateStart,
                $dateEnd,
                $notes
            );
        }
    }
}
