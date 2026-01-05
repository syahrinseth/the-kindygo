<?php

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\Carbon;

class CreateInvoiceForGroup
{
    public function __construct(
        protected AddEnrolmentItemsToInvoice $addEnrolmentItemsToInvoice,
        protected UpdateInvoiceTotals $updateInvoiceTotals,
    ) {}

    public function execute(array $group): ?Invoice
    {
        $parent = $group['parent'];
        $centreId = $group['centre_id'];
        $tenantId = $group['tenant_id'];
        $enrolments = $group['enrolments'];

        $earliestStartDate = $enrolments->min('date_start');
        $invoiceDate = $earliestStartDate ? Carbon::parse($earliestStartDate) : now();

        $invoice = Invoice::create([
            'tenant_id' => $tenantId,
            'centre_id' => $centreId,
            'user_id' => $parent->id,
            'date' => $invoiceDate,
            'due_at' => $invoiceDate->copy()->addDays(7),
            'status' => InvoiceStatus::PENDING->value,
            'total_items' => 0,
            'total_discounts' => 0,
            'total_amount' => 0,
            'total' => 0,
        ]);

        foreach ($enrolments as $enrolment) {
            $this->addEnrolmentItemsToInvoice->execute($invoice, $enrolment);
        }

        $this->updateInvoiceTotals->execute($invoice);

        return $invoice;
    }
}
