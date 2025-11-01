<?php

namespace App\Services;

use App\Models\ChildEnrolment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceItemType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChildEnrolmentInvoiceService
{
    public function generateInvoicesForEnrolment(ChildEnrolment $enrolment): Collection
    {
        return $this->generateInvoicesForEnrolments(collect([$enrolment]));
    }

    public function generateInvoicesForEnrolments(Collection $enrolments): Collection
    {
        $invoices = collect();

        // Group enrolments by parent and centre
        $groupedEnrolments = $this->groupEnrolmentsByParentAndCentre($enrolments);

        foreach ($groupedEnrolments as $groupKey => $group) {
            $invoice = $this->createInvoiceForGroup($group);
            if ($invoice) {
                $invoices->push($invoice);

                // Update enrolment statuses to ACTIVE when invoice is generated
                $this->activateEnrolments($group['enrolments']);
            }
        }

        return $invoices;
    }

    private function groupEnrolmentsByParentAndCentre(Collection $enrolments): array
    {
        $grouped = [];

        foreach ($enrolments as $enrolment) {
            // Get the parent/guardian user
            $parent = $enrolment->child->users()->first();
            if (!$parent) {
                continue; // Skip if no parent found
            }

            // Group by tenant_id + user_id + centre_id
            $groupKey = $enrolment->tenant_id . '_' . $parent->id . '_' . $enrolment->centre_id;

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'parent' => $parent,
                    'centre_id' => $enrolment->centre_id,
                    'tenant_id' => $enrolment->tenant_id,
                    'enrolments' => collect(),
                ];
            }

            $grouped[$groupKey]['enrolments']->push($enrolment);
        }

        return $grouped;
    }

    private function createInvoiceForGroup(array $group): ?Invoice
    {
        $parent = $group['parent'];
        $centreId = $group['centre_id'];
        $tenantId = $group['tenant_id'];
        $enrolments = $group['enrolments'];

        // Get the earliest start date from all enrolments in the group
        $earliestStartDate = $enrolments->min('date_start');
        $invoiceDate = $earliestStartDate ? Carbon::parse($earliestStartDate) : now();

        // Create invoice for this group
        $invoice = Invoice::create([
            'tenant_id' => $tenantId,
            'centre_id' => $centreId,
            'user_id' => $parent->id,
            'date' => $invoiceDate,
            'due_at' => $invoiceDate->copy()->addDays(7), // 7 days payment terms
            'status' => InvoiceStatus::PENDING->value,
            'total_items' => 0,
            'total_discounts' => 0,
            'total_amount' => 0,
            'total' => 0,
        ]);

        // Add invoice items for each enrolment
        foreach ($enrolments as $enrolment) {
            $this->addEnrolmentItemsToInvoice($invoice, $enrolment);
        }

        // Update invoice totals
        $this->updateInvoiceTotals($invoice);

        return $invoice;
    }

    private function addEnrolmentItemsToInvoice(Invoice $invoice, ChildEnrolment $enrolment): void
    {
        // Add main product items
        $this->addProductItemsToInvoice(
            $invoice,
            $enrolment,
            $enrolment->product,
            $enrolment->billed_every,
            $enrolment->date_start,
            $enrolment->date_end
        );

        // Add additional product items
        foreach ($enrolment->additional_products ?? [] as $additionalProduct) {
            if (!isset($additionalProduct['product_id'])) {
                continue;
            }

            $product = Product::find($additionalProduct['product_id']);
            if (!$product) {
                continue;
            }

            $this->addProductItemsToInvoice(
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

    private function addProductItemsToInvoice(
        Invoice $invoice,
        ChildEnrolment $enrolment,
        Product $product,
        ChildEnrolmentBilledEvery $billedEvery,
        Carbon $dateStart,
        ?Carbon $dateEnd,
        ?string $notes = null
    ): void {
        if ($billedEvery === ChildEnrolmentBilledEvery::ONE_TIME) {
            // Create single item for one-time billing
            $this->createInvoiceItem($invoice, $enrolment, $product, $dateStart, $dateEnd, $notes);
        } else {
            // Create items for recurring billing periods
            $this->createRecurringInvoiceItems($invoice, $enrolment, $product, $billedEvery, $dateStart, $dateEnd, $notes);
        }
    }

    private function createRecurringInvoiceItems(
        Invoice $invoice,
        ChildEnrolment $enrolment,
        Product $product,
        ChildEnrolmentBilledEvery $billedEvery,
        Carbon $dateStart,
        ?Carbon $dateEnd,
        ?string $notes = null
    ): void {
        $currentDate = $dateStart->copy();
        // Use enrolment end date if provided, otherwise default to 1 year
        $endDate = $dateEnd ?? Carbon::now()->addYear();
        $invoiceDate = $invoice->date;
        $itemsCreated = 0;

        // Create items for billing periods that should be billed now
        while ($currentDate->lte($endDate) && $itemsCreated < 12) // Limit to 12 periods max
        {
            $periodEnd = $this->calculatePeriodEnd($currentDate, $billedEvery);

            // Ensure period end doesn't exceed enrolment end date
            if ($dateEnd && $periodEnd->gt($dateEnd)) {
                $periodEnd = $dateEnd->copy();
            }

            // Don't create item if period start is beyond enrolment end date
            if ($dateEnd && $currentDate->gt($dateEnd)) {
                break;
            }

            // Create item if this period should be billed now
            if ($this->shouldBillPeriodNow($currentDate, $billedEvery, $invoiceDate)) {
                $this->createInvoiceItem(
                    $invoice,
                    $enrolment,
                    $product,
                    $currentDate->copy(),
                    $periodEnd->copy(),
                    $notes
                );
                $itemsCreated++;

                // For manual invoice generation, only create one period at a time
                if ($itemsCreated >= 1) {
                    break;
                }
            }

            // Move to next billing period
            $currentDate = $this->getNextBillingDate($currentDate, $billedEvery);
        }

        // If no items were created and enrolment hasn't ended, create at least one for the current period
        if ($itemsCreated === 0 && (!$dateEnd || $dateStart->lte($dateEnd))) {
            $periodEnd = $this->calculatePeriodEnd($dateStart, $billedEvery);
            if ($dateEnd && $periodEnd->gt($dateEnd)) {
                $periodEnd = $dateEnd->copy();
            }

            $this->createInvoiceItem(
                $invoice,
                $enrolment,
                $product,
                $dateStart->copy(),
                $periodEnd,
                $notes
            );
        }
    }

    private function shouldBillPeriodNow(Carbon $periodStart, ChildEnrolmentBilledEvery $billedEvery, Carbon $invoiceDate): bool
    {
        // Bill periods that have started or will start within the next 30 days
        $billUntilDate = $invoiceDate->copy()->addDays(30);
        return $periodStart->lte($billUntilDate);
    }

    private function createInvoiceItem(
        Invoice $invoice,
        ChildEnrolment $enrolment,
        Product $product,
        Carbon $periodStart,
        ?Carbon $periodEnd,
        ?string $notes = null
    ): InvoiceItem {
        $description = $product->name;

        if ($periodEnd && !$periodStart->isSameDay($periodEnd)) {
            $description .= " ({$periodStart->format('M j')} - {$periodEnd->format('M j, Y')})";
        } else {
            $description .= " ({$periodStart->format('M j, Y')})";
        }

        if ($notes) {
            $description .= " - {$notes}";
        }

        // Get the current price for this product at this centre
        $productPrice = $product->currentPriceForCentre($enrolment->centre_id);

        if (!$productPrice) {
            // Fallback to any current price if no centre-specific price
            $productPrice = $product->currentPrice;
        }

        // Default to 0 if no price found (you may want to handle this differently)
        $priceInCents = $productPrice ? (int) $productPrice->price : 0;

        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'child_id' => $enrolment->child_id,
            'child_enrolment_id' => $enrolment->id,
            'type' => InvoiceItemType::PRODUCT,
            'name' => $product->name,
            'description' => $description,
            'quantity' => 1,
            'price' => $priceInCents, // Price per unit in cents
            'total' => $priceInCents, // Total = price * quantity (1) in cents
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
    }

    private function updateInvoiceTotals(Invoice $invoice): void
    {
        $totalItems = $invoice->invoiceItems()->count();
        $totalAmount = $invoice->invoiceItems()->sum('total');

        $invoice->update([
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'total' => $totalAmount, // Add tax calculation if needed
        ]);
    }

    private function calculatePeriodEnd(Carbon $periodStart, ChildEnrolmentBilledEvery $billedEvery): Carbon
    {
        return match ($billedEvery) {
            ChildEnrolmentBilledEvery::DAILY => $periodStart->copy(),
            ChildEnrolmentBilledEvery::WEEKLY => $periodStart->copy()->addWeek()->subDay(),
            ChildEnrolmentBilledEvery::MONTHLY => $periodStart->copy()->addMonth()->subDay(),
            ChildEnrolmentBilledEvery::QUARTERLY => $periodStart->copy()->addMonths(3)->subDay(),
            ChildEnrolmentBilledEvery::YEARLY => $periodStart->copy()->addYear()->subDay(),
            default => $periodStart->copy(),
        };
    }

    private function getNextBillingDate(Carbon $currentDate, ChildEnrolmentBilledEvery $billedEvery): Carbon
    {
        return match ($billedEvery) {
            ChildEnrolmentBilledEvery::DAILY => $currentDate->copy()->addDay(),
            ChildEnrolmentBilledEvery::WEEKLY => $currentDate->copy()->addWeek(),
            ChildEnrolmentBilledEvery::MONTHLY => $currentDate->copy()->addMonth(),
            ChildEnrolmentBilledEvery::QUARTERLY => $currentDate->copy()->addMonths(3),
            ChildEnrolmentBilledEvery::YEARLY => $currentDate->copy()->addYear(),
            default => $currentDate->copy()->addMonth(),
        };
    }

    /**
     * Activate enrolments when invoices are generated.
     * This ensures that enrolments are set to ACTIVE status when billing begins.
     *
     * @param Collection $enrolments
     * @return void
     */
    private function activateEnrolments(Collection $enrolments): void
    {
        foreach ($enrolments as $enrolment) {
            // Only update status if enrolment is not already active
            // This prevents overriding other statuses like COMPLETED or CANCELLED
            if ($enrolment->status !== ChildEnrolmentStatus::ACTIVE) {
                // Only activate if enrolment is in a state that allows activation
                $allowedStatuses = [
                    ChildEnrolmentStatus::DRAFT,
                    ChildEnrolmentStatus::PENDING,
                    ChildEnrolmentStatus::INACTIVE
                ];

                if (in_array($enrolment->status, $allowedStatuses)) {
                    $enrolment->update(['status' => ChildEnrolmentStatus::ACTIVE]);
                }
            }
        }
    }

    public function getRelatedEnrolments(ChildEnrolment $enrolment): Collection|null
    {
        // Get parent/guardian user
        $parent = $enrolment->child->users()->first();
        if (!$parent) {
            return null; // No parent found, cannot generate invoices
        }

        // Find all active enrolments for the same tenant, parent, and centre
        $groupedEnrolments = ChildEnrolment::where('tenant_id', $enrolment->tenant_id)
            ->where('centre_id', $enrolment->centre_id)
            ->where('status', \App\Enums\ChildEnrolmentStatus::ACTIVE)
            ->whereHas('child.users', function ($query) use ($parent) {
                $query->where('users.id', $parent->id);
            })
            ->get();

        // Filter out enrolments that have ended or don't need invoicing
        $enrolmentsNeedingInvoice = $groupedEnrolments->filter(function ($enrolment) {
            // Check if enrolment has ended
            if ($enrolment->date_end && Carbon::parse($enrolment->date_end)->lt(Carbon::now())) {
                return false;
            }

            // Check if there's already an invoice item for the upcoming billing period
            $nextBillingDate = $this->getNextBillingPeriodStart($enrolment);
            if (!$nextBillingDate) {
                return false;
            }

            // Check if invoice item already exists for this period
            $existingItem = $enrolment->invoiceItems()
                ->whereDate('period_start', '>=', $nextBillingDate->toDateString())
                ->exists();

            return !$existingItem;
        });

        if ($enrolmentsNeedingInvoice->isEmpty()) {
            return null; // No enrolments need invoicing
        }

        return $enrolmentsNeedingInvoice;
    }

    public function getNextBillingPeriodStart(ChildEnrolment $enrolment): ?Carbon
    {
        $startDate = Carbon::parse($enrolment->date_start);
        $today = now();
        $endDate = $enrolment->date_end ? Carbon::parse($enrolment->date_end) : null;

        // If enrolment hasn't started yet, use the start date
        if ($today->lt($startDate)) {
            // But only if enrolment hasn't ended before it started
            if ($endDate && $startDate->gt($endDate)) {
                return null;
            }
            return $startDate;
        }

        // For one-time billing, return start date only if within enrolment period
        if ($enrolment->billed_every === ChildEnrolmentBilledEvery::ONE_TIME) {
            // Check if already billed
            $existingItem = $enrolment->invoiceItems()->first();
            if ($existingItem) {
                return null;
            }
            // Only return start date if enrolment hasn't ended
            return (!$endDate || $startDate->lte($endDate)) ? $startDate : null;
        }

        // Calculate next billing period based on frequency
        $nextDate = match ($enrolment->billed_every) {
            ChildEnrolmentBilledEvery::YEARLY => $this->getNextYearlyBillingDate($startDate, $today),
            ChildEnrolmentBilledEvery::QUARTERLY => $this->getNextQuarterlyBillingDate($startDate, $today),
            ChildEnrolmentBilledEvery::MONTHLY => $this->getNextMonthlyBillingDate($startDate, $today),
            ChildEnrolmentBilledEvery::WEEKLY => $this->getNextWeeklyBillingDate($startDate, $today),
            ChildEnrolmentBilledEvery::DAILY => $today->copy()->addDay(),
            default => null,
        };

        // Don't return billing dates that are after the enrolment end date
        if ($nextDate && $endDate && $nextDate->gt($endDate)) {
            return null;
        }

        return $nextDate;
    }

    // Add these new helper methods for consistency
    private function getNextYearlyBillingDate(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addYear();
        }
        return $nextDate;
    }

    private function getNextQuarterlyBillingDate(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addMonths(3);
        }
        return $nextDate;
    }

    private function getNextMonthlyBillingDate(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addMonth();
        }
        return $nextDate;
    }

    private function getNextWeeklyBillingDate(Carbon $startDate, Carbon $today): Carbon
    {
        $nextDate = $startDate->copy();
        while ($nextDate->lte($today)) {
            $nextDate->addWeek();
        }
        return $nextDate;
    }

    /**
     * Check if an enrolment needs invoicing based on the days-ahead parameter
     */
    public function shouldGenerateInvoices(ChildEnrolment $enrolment, int $daysAhead): bool
    {
        // Get parent/guardian - skip if no parent found
        $parent = $enrolment->child->users()->first();
        if (!$parent) {
            return false;
        }

        // Check if enrolment has ended
        if ($enrolment->date_end && Carbon::parse($enrolment->date_end)->lt(Carbon::now())) {
            return false;
        }

        // Get the next billing period start date
        $nextBillingPeriodStart = $this->getNextBillingPeriodStart($enrolment);

        if (!$nextBillingPeriodStart) {
            return false;
        }

        // Don't generate invoices for periods that start after the enrolment ends
        if ($enrolment->date_end && $nextBillingPeriodStart->gt(Carbon::parse($enrolment->date_end))) {
            return false;
        }

        // Check if we should bill this period now (within days ahead)
        $billUntilDate = Carbon::now()->addDays($daysAhead);
        $shouldBill = $nextBillingPeriodStart->lte($billUntilDate);

        if (!$shouldBill) {
            return false;
        }

        // Check if invoice item already exists for this billing period
        return !$this->hasExistingInvoiceItemForPeriod($enrolment, $nextBillingPeriodStart);
    }

    /**
     * Check if an invoice item already exists for a specific billing period
     */
    public function hasExistingInvoiceItemForPeriod(ChildEnrolment $enrolment, Carbon $periodStart): bool
    {
        return $enrolment->invoiceItems()
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();
    }
}
