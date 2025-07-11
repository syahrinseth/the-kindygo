<?php

namespace App\Services;

use App\Models\ChildEnrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Enums\ChildEnrollmentBilledEvery;
use App\Enums\ChildEnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceItemType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChildEnrollmentInvoiceService
{
    public function generateInvoicesForEnrollment(ChildEnrollment $enrollment): Collection
    {
        return $this->generateInvoicesForEnrollments(collect([$enrollment]));
    }

    public function generateInvoicesForEnrollments(Collection $enrollments): Collection
    {
        $invoices = collect();
        
        // Group enrollments by parent and centre
        $groupedEnrollments = $this->groupEnrollmentsByParentAndCentre($enrollments);
        
        foreach ($groupedEnrollments as $groupKey => $group) {
            $invoice = $this->createInvoiceForGroup($group);
            if ($invoice) {
                $invoices->push($invoice);
                
                // Update enrollment statuses to ACTIVE when invoice is generated
                $this->activateEnrollments($group['enrollments']);
            }
        }
        
        return $invoices;
    }

    private function groupEnrollmentsByParentAndCentre(Collection $enrollments): array
    {
        $grouped = [];
        
        foreach ($enrollments as $enrollment) {
            // Get the parent/guardian user
            $parent = $enrollment->child->users()->first();
            if (!$parent) {
                continue; // Skip if no parent found
            }
            
            // Group by tenant_id + user_id + centre_id
            $groupKey = $enrollment->tenant_id . '_' . $parent->id . '_' . $enrollment->centre_id;
            
            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'parent' => $parent,
                    'centre_id' => $enrollment->centre_id,
                    'tenant_id' => $enrollment->tenant_id,
                    'enrollments' => collect(),
                ];
            }
            
            $grouped[$groupKey]['enrollments']->push($enrollment);
        }
        
        return $grouped;
    }

    private function createInvoiceForGroup(array $group): ?Invoice
    {
        $parent = $group['parent'];
        $centreId = $group['centre_id'];
        $tenantId = $group['tenant_id'];
        $enrollments = $group['enrollments'];

        // Get the earliest start date from all enrollments in the group
        $earliestStartDate = $enrollments->min('date_start');
        $invoiceDate = $earliestStartDate ? Carbon::parse($earliestStartDate) : now();
        
        // Create invoice for this group
        $invoice = Invoice::create([
            'tenant_id' => $tenantId,
            'centre_id' => $centreId,
            'user_id' => $parent->id,
            'date' => $invoiceDate,
            'due_at' => $invoiceDate->copy()->addDays(30), // 30 days payment terms
            'status' => InvoiceStatus::PENDING->value,
            'total_items' => 0,
            'total_discounts' => 0,
            'total_amount' => 0,
            'total' => 0,
        ]);
        
        // Add invoice items for each enrollment
        foreach ($enrollments as $enrollment) {
            $this->addEnrollmentItemsToInvoice($invoice, $enrollment);
        }
        
        // Update invoice totals
        $this->updateInvoiceTotals($invoice);
        
        return $invoice;
    }

    private function addEnrollmentItemsToInvoice(Invoice $invoice, ChildEnrollment $enrollment): void
    {
        // Add main product items
        $this->addProductItemsToInvoice(
            $invoice, 
            $enrollment, 
            $enrollment->product, 
            $enrollment->billed_every, 
            $enrollment->date_start, 
            $enrollment->date_end
        );
        
        // Add additional product items
        foreach ($enrollment->additional_products ?? [] as $additionalProduct) {
            if (!isset($additionalProduct['product_id'])) {
                continue;
            }
            
            $product = Product::find($additionalProduct['product_id']);
            if (!$product) {
                continue;
            }
            
            $this->addProductItemsToInvoice(
                $invoice,
                $enrollment,
                $product,
                ChildEnrollmentBilledEvery::from($additionalProduct['billed_every']),
                Carbon::parse($additionalProduct['date_start']),
                isset($additionalProduct['date_end']) ? Carbon::parse($additionalProduct['date_end']) : null,
                $additionalProduct['notes'] ?? null
            );
        }
    }

    private function addProductItemsToInvoice(
        Invoice $invoice,
        ChildEnrollment $enrollment,
        Product $product,
        ChildEnrollmentBilledEvery $billedEvery,
        Carbon $dateStart,
        ?Carbon $dateEnd,
        ?string $notes = null
    ): void {
        if ($billedEvery === ChildEnrollmentBilledEvery::ONE_TIME) {
            // Create single item for one-time billing
            $this->createInvoiceItem($invoice, $enrollment, $product, $dateStart, $dateEnd, $notes);
        } else {
            // Create items for recurring billing periods
            $this->createRecurringInvoiceItems($invoice, $enrollment, $product, $billedEvery, $dateStart, $dateEnd, $notes);
        }
    }

    private function createRecurringInvoiceItems(
        Invoice $invoice,
        ChildEnrollment $enrollment,
        Product $product,
        ChildEnrollmentBilledEvery $billedEvery,
        Carbon $dateStart,
        ?Carbon $dateEnd,
        ?string $notes = null
    ): void {
        $currentDate = $dateStart->copy();
        // Use enrollment end date if provided, otherwise default to 1 year
        $endDate = $dateEnd ?? Carbon::now()->addYear();
        $invoiceDate = $invoice->date;
        $itemsCreated = 0;
        
        // Create items for billing periods that should be billed now
        while ($currentDate->lte($endDate) && $itemsCreated < 12) // Limit to 12 periods max
        {
            $periodEnd = $this->calculatePeriodEnd($currentDate, $billedEvery);
            
            // Ensure period end doesn't exceed enrollment end date
            if ($dateEnd && $periodEnd->gt($dateEnd)) {
                $periodEnd = $dateEnd->copy();
            }
            
            // Don't create item if period start is beyond enrollment end date
            if ($dateEnd && $currentDate->gt($dateEnd)) {
                break;
            }
            
            // Create item if this period should be billed now
            if ($this->shouldBillPeriodNow($currentDate, $billedEvery, $invoiceDate)) {
                $this->createInvoiceItem(
                    $invoice,
                    $enrollment,
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
        
        // If no items were created and enrollment hasn't ended, create at least one for the current period
        if ($itemsCreated === 0 && (!$dateEnd || $dateStart->lte($dateEnd))) {
            $periodEnd = $this->calculatePeriodEnd($dateStart, $billedEvery);
            if ($dateEnd && $periodEnd->gt($dateEnd)) {
                $periodEnd = $dateEnd->copy();
            }
            
            $this->createInvoiceItem(
                $invoice,
                $enrollment,
                $product,
                $dateStart->copy(),
                $periodEnd,
                $notes
            );
        }
    }

    private function shouldBillPeriodNow(Carbon $periodStart, ChildEnrollmentBilledEvery $billedEvery, Carbon $invoiceDate): bool
    {
        // Bill periods that have started or will start within the next 30 days
        $billUntilDate = $invoiceDate->copy()->addDays(30);
        return $periodStart->lte($billUntilDate);
    }

    private function createInvoiceItem(
        Invoice $invoice,
        ChildEnrollment $enrollment,
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
        $productPrice = $product->currentPriceForCentre($enrollment->centre_id);
        
        if (!$productPrice) {
            // Fallback to any current price if no centre-specific price
            $productPrice = $product->currentPrice;
        }
        
        // Default to 0 if no price found (you may want to handle this differently)
        $priceInCents = $productPrice ? (int) $productPrice->price : 0;
        
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'child_id' => $enrollment->child_id,
            'child_enrollment_id' => $enrollment->id,
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

    private function calculatePeriodEnd(Carbon $periodStart, ChildEnrollmentBilledEvery $billedEvery): Carbon
    {
        return match ($billedEvery) {
            ChildEnrollmentBilledEvery::DAILY => $periodStart->copy(),
            ChildEnrollmentBilledEvery::WEEKLY => $periodStart->copy()->addWeek()->subDay(),
            ChildEnrollmentBilledEvery::MONTHLY => $periodStart->copy()->addMonth()->subDay(),
            ChildEnrollmentBilledEvery::QUARTERLY => $periodStart->copy()->addMonths(3)->subDay(),
            ChildEnrollmentBilledEvery::YEARLY => $periodStart->copy()->addYear()->subDay(),
            default => $periodStart->copy(),
        };
    }
    
    private function getNextBillingDate(Carbon $currentDate, ChildEnrollmentBilledEvery $billedEvery): Carbon
    {
        return match ($billedEvery) {
            ChildEnrollmentBilledEvery::DAILY => $currentDate->copy()->addDay(),
            ChildEnrollmentBilledEvery::WEEKLY => $currentDate->copy()->addWeek(),
            ChildEnrollmentBilledEvery::MONTHLY => $currentDate->copy()->addMonth(),
            ChildEnrollmentBilledEvery::QUARTERLY => $currentDate->copy()->addMonths(3),
            ChildEnrollmentBilledEvery::YEARLY => $currentDate->copy()->addYear(),
            default => $currentDate->copy()->addMonth(),
        };
    }
    
    /**
     * Activate enrollments when invoices are generated.
     * This ensures that enrollments are set to ACTIVE status when billing begins.
     *
     * @param Collection $enrollments
     * @return void
     */
    private function activateEnrollments(Collection $enrollments): void
    {
        foreach ($enrollments as $enrollment) {
            // Only update status if enrollment is not already active
            // This prevents overriding other statuses like COMPLETED or CANCELLED
            if ($enrollment->status !== ChildEnrollmentStatus::ACTIVE) {
                // Only activate if enrollment is in a state that allows activation
                $allowedStatuses = [
                    ChildEnrollmentStatus::DRAFT,
                    ChildEnrollmentStatus::PENDING,
                    ChildEnrollmentStatus::INACTIVE
                ];
                
                if (in_array($enrollment->status, $allowedStatuses)) {
                    $enrollment->update(['status' => ChildEnrollmentStatus::ACTIVE]);
                }
            }
        }
    }

    public function getRelatedEnrollments(ChildEnrollment $enrollment): Collection|null
    {
        // Get parent/guardian user
        $parent = $enrollment->child->users()->first();
        if (!$parent) {
            return null; // No parent found, cannot generate invoices
        }

        // Find all active enrollments for the same tenant, parent, and centre
        $groupedEnrollments = ChildEnrollment::where('tenant_id', $enrollment->tenant_id)
            ->where('centre_id', $enrollment->centre_id)
            ->where('status', \App\Enums\ChildEnrollmentStatus::ACTIVE)
            ->whereHas('child.users', function ($query) use ($parent) {
                $query->where('users.id', $parent->id);
            })
            ->get();

        // Filter out enrollments that have ended or don't need invoicing
        $enrollmentsNeedingInvoice = $groupedEnrollments->filter(function ($enrollment) {
            // Check if enrollment has ended
            if ($enrollment->date_end && Carbon::parse($enrollment->date_end)->lt(Carbon::now())) {
                return false;
            }

            // Check if there's already an invoice item for the upcoming billing period
            $nextBillingDate = $this->getNextBillingPeriodStart($enrollment);
            if (!$nextBillingDate) {
                return false;
            }

            // Check if invoice item already exists for this period
            $existingItem = $enrollment->invoiceItems()
                ->whereDate('period_start', '>=', $nextBillingDate->toDateString())
                ->exists();
                
            return !$existingItem;
        });

        if ($enrollmentsNeedingInvoice->isEmpty()) {
            return null; // No enrollments need invoicing
        }

        return $enrollmentsNeedingInvoice;
    }

    public function getNextBillingPeriodStart(ChildEnrollment $enrollment): ?Carbon
    {
        $startDate = Carbon::parse($enrollment->date_start);
        $today = now();
        $endDate = $enrollment->date_end ? Carbon::parse($enrollment->date_end) : null;
        
        // If enrollment hasn't started yet, use the start date
        if ($today->lt($startDate)) {
            // But only if enrollment hasn't ended before it started
            if ($endDate && $startDate->gt($endDate)) {
                return null;
            }
            return $startDate;
        }

        // For one-time billing, return start date only if within enrollment period
        if ($enrollment->billed_every === ChildEnrollmentBilledEvery::ONE_TIME) {
            // Check if already billed
            $existingItem = $enrollment->invoiceItems()->first();
            if ($existingItem) {
                return null;
            }
            // Only return start date if enrollment hasn't ended
            return (!$endDate || $startDate->lte($endDate)) ? $startDate : null;
        }
        
        // Calculate next billing period based on frequency
        $nextDate = match ($enrollment->billed_every) {
            ChildEnrollmentBilledEvery::YEARLY => $this->getNextYearlyBillingDate($startDate, $today),
            ChildEnrollmentBilledEvery::QUARTERLY => $this->getNextQuarterlyBillingDate($startDate, $today),
            ChildEnrollmentBilledEvery::MONTHLY => $this->getNextMonthlyBillingDate($startDate, $today),
            ChildEnrollmentBilledEvery::WEEKLY => $this->getNextWeeklyBillingDate($startDate, $today),
            ChildEnrollmentBilledEvery::DAILY => $today->copy()->addDay(),
            default => null,
        };

        // Don't return billing dates that are after the enrollment end date
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
     * Check if an enrollment needs invoicing based on the days-ahead parameter
     */
    public function shouldGenerateInvoices(ChildEnrollment $enrollment, int $daysAhead): bool
    {
        // Get parent/guardian - skip if no parent found
        $parent = $enrollment->child->users()->first();
        if (!$parent) {
            return false;
        }

        // Check if enrollment has ended
        if ($enrollment->date_end && Carbon::parse($enrollment->date_end)->lt(Carbon::now())) {
            return false;
        }
        
        // Get the next billing period start date
        $nextBillingPeriodStart = $this->getNextBillingPeriodStart($enrollment);
        
        if (!$nextBillingPeriodStart) {
            return false;
        }

        // Don't generate invoices for periods that start after the enrollment ends
        if ($enrollment->date_end && $nextBillingPeriodStart->gt(Carbon::parse($enrollment->date_end))) {
            return false;
        }
        
        // Check if we should bill this period now (within days ahead)
        $billUntilDate = Carbon::now()->addDays($daysAhead);
        $shouldBill = $nextBillingPeriodStart->lte($billUntilDate);
        
        if (!$shouldBill) {
            return false;
        }
        
        // Check if invoice item already exists for this billing period
        return !$this->hasExistingInvoiceItemForPeriod($enrollment, $nextBillingPeriodStart);
    }

    /**
     * Check if an invoice item already exists for a specific billing period
     */
    public function hasExistingInvoiceItemForPeriod(ChildEnrollment $enrollment, Carbon $periodStart): bool
    {
        return $enrollment->invoiceItems()
            ->whereDate('period_start', $periodStart->toDateString())
            ->exists();
    }
}
