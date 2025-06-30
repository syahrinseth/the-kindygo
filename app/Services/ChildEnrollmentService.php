<?php

namespace App\Services;

use App\Models\ChildEnrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Enums\ChildEnrollmentStatus;
use App\Enums\ChildEnrollmentBilledEvery;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceItemType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChildEnrollmentService
{
    /**
     * Generate invoices for all active enrollments based on their billing cycle.
     * This method is designed to be run by a scheduler.
     *
     * @param Carbon|null $forDate The date to generate invoices for (defaults to today)
     * @param Carbon|null $effectiveDate The effective date to use for invoice items (defaults to $forDate)
     * @return array Summary of invoice generation results
     */
    public function generateRecurringInvoices(?Carbon $forDate = null, ?Carbon $effectiveDate = null): array
    {
        $forDate = $forDate ?? now();
        $results = [
            'total_processed' => 0,
            'invoices_created' => 0,
            'errors' => [],
            'skipped' => [],
            'by_frequency' => []
        ];

        Log::info('Starting recurring invoice generation', ['date' => $forDate->toDateString()]);

        try {
            // Get all active enrollments that need invoicing
            $enrollments = $this->getEnrollmentsDueForInvoicing($forDate);
            $results['total_processed'] = $enrollments->count();

            Log::info('Found enrollments for processing', ['count' => $enrollments->count()]);

            // Group enrollments by parent/user and centre for consolidated invoices
            $groupedEnrollments = $this->groupEnrollmentsForInvoicing($enrollments);

            foreach ($groupedEnrollments as $groupKey => $group) {
                try {
                    $invoice = $this->createInvoiceForEnrollmentGroup($group, $forDate, $effectiveDate);
                    if ($invoice) {
                        $results['invoices_created']++;
                        
                        // Track by frequency
                        foreach ($group['enrollments'] as $enrollment) {
                            $frequency = $enrollment->billed_every->value;
                            $results['by_frequency'][$frequency] = ($results['by_frequency'][$frequency] ?? 0) + 1;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create invoice for group', [
                        'group_key' => $groupKey,
                        'error' => $e->getMessage()
                    ]);
                    $results['errors'][] = "Group {$groupKey}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate recurring invoices', ['error' => $e->getMessage()]);
            $results['errors'][] = $e->getMessage();
        }

        Log::info('Completed recurring invoice generation', $results);
        return $results;
    }

    /**
     * Get enrollments that are due for invoicing on the given date.
     *
     * @param Carbon $forDate
     * @return Collection
     */
    public function getEnrollmentsDueForInvoicing(Carbon $forDate): Collection
    {
        return ChildEnrollment::active()
            ->current()
            ->with(['child.users', 'centre', 'product'])
            ->get()
            ->filter(function ($enrollment) use ($forDate) {
                return $this->isEnrollmentDueForInvoicing($enrollment, $forDate);
            });
    }

    /**
     * Check if an enrollment is due for invoicing on the given date.
     *
     * @param ChildEnrollment $enrollment
     * @param Carbon $forDate
     * @return bool
     */
    protected function isEnrollmentDueForInvoicing(ChildEnrollment $enrollment, Carbon $forDate): bool
    {
        // Skip one-time billing enrollments (they should be invoiced separately)
        if ($enrollment->billed_every === ChildEnrollmentBilledEvery::ONE_TIME) {
            return false;
        }

        // Use only date components for comparison, ignoring time
        $startDate = $enrollment->date_start->startOfDay();
        $checkDate = $forDate->copy()->startOfDay();

        // Check if we've passed the start date
        if ($checkDate->lt($startDate)) {
            return false;
        }

        // Check if enrollment has ended
        if ($enrollment->date_end && $checkDate->gt($enrollment->date_end->startOfDay())) {
            return false;
        }
        
        // Calculate if this date matches the billing cycle
        return $this->matchesBillingCycle($enrollment, $forDate);
    }

    /**
     * Check if the given date matches the enrollment's billing cycle.
     *
     * @param ChildEnrollment $enrollment
     * @param Carbon $forDate
     * @return bool
     */
    protected function matchesBillingCycle(ChildEnrollment $enrollment, Carbon $forDate): bool
    {
        // Use only date components for comparison, ignoring time
        $startDate = $enrollment->date_start->startOfDay();
        $checkDate = $forDate->copy()->startOfDay();
        
        switch ($enrollment->billed_every) {
            case ChildEnrollmentBilledEvery::DAILY:
                return true; // Every day
                
            case ChildEnrollmentBilledEvery::WEEKLY:
                // Same day of week as start date
                return $checkDate->dayOfWeek === $startDate->dayOfWeek &&
                       $checkDate->diffInWeeks($startDate) >= 0;
                       
            case ChildEnrollmentBilledEvery::MONTHLY:
                // Same day of month as start date (or last day if start day doesn't exist)
                $targetDay = min($startDate->day, $checkDate->daysInMonth);
                
                return $checkDate->day === $targetDay &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case ChildEnrollmentBilledEvery::QUARTERLY:
                // Every 3 months on the same day
                return $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInMonths($startDate) % 3 === 0 &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case ChildEnrollmentBilledEvery::YEARLY:
                // Same month and day each year
                return $checkDate->month === $startDate->month &&
                       $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInYears($startDate) >= 0;
                       
            default:
                return false;
        }
    }

    /**
     * Group enrollments by parent/user and centre for consolidated invoicing.
     *
     * @param Collection $enrollments
     * @return array
     */
    public function groupEnrollmentsForInvoicing(Collection $enrollments): array
    {
        $groups = [];

        foreach ($enrollments as $enrollment) {
            // Get the primary parent/user for this child
            $primaryUser = $enrollment->child?->users?->first();
            
            if (!$primaryUser) {
                Log::warning('No parent/user found for child', [
                    'child_id' => $enrollment->child_id,
                    'enrollment_id' => $enrollment->id
                ]);
                continue;
            }

            // Group by user_id, tenant_id, and centre_id
            $groupKey = "{$primaryUser->id}-{$enrollment->tenant_id}-{$enrollment->centre_id}";

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'user' => $primaryUser,
                    'tenant_id' => $enrollment->tenant_id,
                    'centre_id' => $enrollment->centre_id,
                    'centre' => $enrollment->centre,
                    'enrollments' => []
                ];
            }

            $groups[$groupKey]['enrollments'][] = $enrollment;
        }

        return $groups;
    }

    /**
     * Create an invoice for a group of enrollments.
     *
     * @param array $group
     * @param Carbon $forDate
     * @param Carbon|null $effectiveDate
     * @return Invoice|null
     */
    public function createInvoiceForEnrollmentGroup(array $group, Carbon $forDate, ?Carbon $effectiveDate = null): ?Invoice
    {
        // Use effective date if provided, otherwise use forDate
        $effectiveDate = $effectiveDate ?? $forDate;
        
        DB::beginTransaction();

        try {
            // Check if invoice already exists for this period
            if ($this->invoiceExistsForPeriod($group, $forDate)) {
                Log::info('Invoice already exists for period', [
                    'user_id' => $group['user']->id,
                    'centre_id' => $group['centre_id'],
                    'date' => $forDate->toDateString()
                ]);
                DB::rollBack();
                return null;
            }

            // Create the invoice
            $invoice = Invoice::create([
                'tenant_id' => $group['tenant_id'],
                'centre_id' => $group['centre_id'],
                'user_id' => $group['user']->id,
                'date' => $forDate,
                'due_at' => $this->calculateDueDate($forDate),
                'status' => InvoiceStatus::PENDING,
                'total_items' => 0,
                'total_discounts' => 0,
                'total_amount' => 0,
                'total' => 0,
            ]);

            $totalAmount = 0;

            // Create invoice items for each enrollment
            foreach ($group['enrollments'] as $enrollment) {
                $price = $this->getEnrollmentPrice($enrollment, $forDate);
                
                if ($price <= 0) {
                    Log::warning('No valid price found for enrollment', [
                        'enrollment_id' => $enrollment->id,
                        'product_id' => $enrollment->product_id
                    ]);
                    continue;
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $enrollment->product_id,
                    'child_id' => $enrollment->child_id,
                    'name' => $this->generateInvoiceItemName($enrollment),
                    'price' => $price,
                    'quantity' => 1,
                    'discount' => 0,
                    'total' => $price,
                    'type' => InvoiceItemType::PRODUCT,
                    'paid_amount' => 0,
                    'balance_amount' => $price,
                    'paid' => false,
                    'effective_date' => $effectiveDate ?? $forDate,
                ]);

                $totalAmount += $price;
            }

            // Update invoice totals
            $invoice->update([
                'total_items' => $totalAmount,
                'total_amount' => $totalAmount,
                'total' => $totalAmount,
            ]);

            DB::commit();

            Log::info('Created invoice for enrollment group', [
                'invoice_id' => $invoice->id,
                'user_id' => $group['user']->id,
                'centre_id' => $group['centre_id'],
                'total_amount' => $totalAmount,
                'enrollments_count' => count($group['enrollments'])
            ]);

            return $invoice;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create invoice for enrollment group', [
                'user_id' => $group['user']->id,
                'centre_id' => $group['centre_id'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Check if an invoice already exists for the given period.
     *
     * @param array $group
     * @param Carbon $forDate
     * @return bool
     */
    protected function invoiceExistsForPeriod(array $group, Carbon $forDate): bool
    {
        return Invoice::where('tenant_id', $group['tenant_id'])
            ->where('centre_id', $group['centre_id'])
            ->where('user_id', $group['user']->id)
            ->whereDate('date', $forDate)
            ->exists();
    }

    /**
     * Get the price for an enrollment on a specific date.
     *
     * @param ChildEnrollment $enrollment
     * @param Carbon $forDate
     * @return int Price in cents
     */
    protected function getEnrollmentPrice(ChildEnrollment $enrollment, Carbon $forDate): int
    {
        // Get the product price for the given date and centre
        $productPrice = $enrollment->product->getPriceForCentre($forDate, $enrollment->centre_id);
        
        if (!$productPrice) {
            // Fallback to general price for the date
            $productPrice = $enrollment->product->getPriceOn($forDate);
        }

        return $productPrice ? $productPrice->price : 0;
    }

    /**
     * Generate a descriptive name for the invoice item.
     *
     * @param ChildEnrollment $enrollment
     * @return string
     */
    protected function generateInvoiceItemName(ChildEnrollment $enrollment): string
    {
        $childName = $enrollment->child->first_name . ' ' . $enrollment->child->last_name;
        $productName = $enrollment->product->name;
        $billingPeriod = $this->getBillingPeriodText($enrollment->billed_every);
        
        return "{$productName} - {$childName} ({$billingPeriod})";
    }

    /**
     * Get human-readable billing period text.
     *
     * @param ChildEnrollmentBilledEvery $billedEvery
     * @return string
     */
    protected function getBillingPeriodText(ChildEnrollmentBilledEvery $billedEvery): string
    {
        return match($billedEvery) {
            ChildEnrollmentBilledEvery::DAILY => 'Daily',
            ChildEnrollmentBilledEvery::WEEKLY => 'Weekly',
            ChildEnrollmentBilledEvery::MONTHLY => 'Monthly',
            ChildEnrollmentBilledEvery::QUARTERLY => 'Quarterly',
            ChildEnrollmentBilledEvery::YEARLY => 'Yearly',
            ChildEnrollmentBilledEvery::ONE_TIME => 'One-time',
        };
    }

    /**
     * Calculate the due date for an invoice.
     *
     * @param Carbon $invoiceDate
     * @return Carbon
     */
    protected function calculateDueDate(Carbon $invoiceDate): Carbon
    {
        // Default to 30 days from invoice date
        // This could be made configurable per tenant/centre
        return $invoiceDate->copy()->addDays(30);
    }

    /**
     * Generate invoices for one-time billing enrollments.
     * This can be called manually or when an enrollment is activated.
     *
     * @param Collection|null $enrollments Specific enrollments to process
     * @param Carbon|null $effectiveDate The effective date to use for invoice items (defaults to today)
     * @return array Summary of results
     */
    public function generateOneTimeInvoices(?Collection $enrollments = null, ?Carbon $effectiveDate = null): array
    {
        $results = [
            'total_processed' => 0,
            'invoices_created' => 0,
            'errors' => []
        ];

        if ($enrollments === null) {
            $enrollments = ChildEnrollment::active()
                ->current()
                ->where('billed_every', ChildEnrollmentBilledEvery::ONE_TIME)
                ->with(['child.users', 'centre', 'product'])
                ->get();
        }

        $results['total_processed'] = $enrollments->count();

        // Group enrollments for consolidated invoicing
        $groupedEnrollments = $this->groupEnrollmentsForInvoicing($enrollments);

        foreach ($groupedEnrollments as $groupKey => $group) {
            try {
                $invoice = $this->createInvoiceForEnrollmentGroup($group, now(), $effectiveDate);
                if ($invoice) {
                    $results['invoices_created']++;
                }
            } catch (\Exception $e) {
                Log::error('Failed to create one-time invoice for group', [
                    'group_key' => $groupKey,
                    'error' => $e->getMessage()
                ]);
                $results['errors'][] = "Group {$groupKey}: " . $e->getMessage();
            }
        }

        return $results;
    }
}