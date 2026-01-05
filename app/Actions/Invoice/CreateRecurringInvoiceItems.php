<?php

namespace App\Actions\Invoice;

use App\Actions\ChildEnrolment\CalculatePeriodEnd;
use App\Actions\ChildEnrolment\GetNextBillingDate;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;

class CreateRecurringInvoiceItems
{
    public function __construct(
        protected CreateInvoiceItem $createInvoiceItem,
        protected CalculatePeriodEnd $calculatePeriodEnd,
        protected GetNextBillingDate $getNextBillingDate,
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
        $currentDate = $dateStart->copy();
        $endDate = $dateEnd ?? Carbon::now()->addYear();
        $invoiceDate = $invoice->date;
        $itemsCreated = 0;

        while ($currentDate->lte($endDate) && $itemsCreated < 12) {
            $periodEnd = $this->calculatePeriodEnd->execute($currentDate, $billedEvery);

            if ($dateEnd && $periodEnd->gt($dateEnd)) {
                $periodEnd = $dateEnd->copy();
            }

            if ($dateEnd && $currentDate->gt($dateEnd)) {
                break;
            }

            if ($this->shouldBillPeriodNow($currentDate, $invoiceDate)) {
                $this->createInvoiceItem->execute(
                    $invoice,
                    $enrolment,
                    $product,
                    $currentDate->copy(),
                    $periodEnd->copy(),
                    $notes
                );
                $itemsCreated++;

                if ($itemsCreated >= 1) {
                    break;
                }
            }

            $currentDate = $this->getNextBillingDate->execute($currentDate, $billedEvery);
        }

        if ($itemsCreated === 0 && (! $dateEnd || $dateStart->lte($dateEnd))) {
            $periodEnd = $this->calculatePeriodEnd->execute($dateStart, $billedEvery);
            if ($dateEnd && $periodEnd->gt($dateEnd)) {
                $periodEnd = $dateEnd->copy();
            }

            $this->createInvoiceItem->execute(
                $invoice,
                $enrolment,
                $product,
                $dateStart->copy(),
                $periodEnd,
                $notes
            );
        }
    }

    private function shouldBillPeriodNow(Carbon $periodStart, Carbon $invoiceDate): bool
    {
        $billUntilDate = $invoiceDate->copy()->addDays(30);

        return $periodStart->lte($billUntilDate);
    }
}
