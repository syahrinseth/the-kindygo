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
                // Main product
                $price = $this->getEnrollmentPrice($enrollment, $forDate);
                
                if ($price <= 0) {
                    Log::warning('No valid price found for enrollment', [
                        'enrollment_id' => $enrollment->id,
                        'product_id' => $enrollment->product_id
                    ]);
                    continue;
                }

                $invoiceItem = InvoiceItem::create([
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

                // Create the pivot relationship between enrollment and invoice item
                $enrollment->invoiceItems()->attach($invoiceItem->id, [
                    'quantity' => 1,
                    'notes' => 'Main enrollment product',
                ]);

                $totalAmount += $price;

                // Additional products
                $additionalProducts = $enrollment->additional_products ?? [];
                foreach ($additionalProducts as $additionalProduct) {
                    if (!isset($additionalProduct['product_id'])) {
                        continue;
                    }

                    // Check if this additional product is due for billing
                    // For one-time products, we need different logic
                    $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
                    $shouldInclude = false;
                    
                    if ($billedEvery === ChildEnrollmentBilledEvery::ONE_TIME->value) {
                        // For one-time products, check if they haven't been invoiced yet
                        // This is a simplified check - in production you might want to track invoiced items
                        $shouldInclude = true;
                    } else {
                        // For recurring products, use the normal billing cycle check
                        $shouldInclude = $this->isAdditionalProductDueForInvoicing($additionalProduct, $forDate, $enrollment);
                    }
                    
                    if (!$shouldInclude) {
                        continue;
                    }

                    $additionalPrice = $this->getAdditionalProductPrice($additionalProduct, $forDate);
                    if ($additionalPrice <= 0) {
                        Log::warning('No valid price found for additional product', [
                            'enrollment_id' => $enrollment->id,
                            'additional_product_id' => $additionalProduct['product_id']
                        ]);
                        continue;
                    }

                    $additionalInvoiceItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $additionalProduct['product_id'],
                        'child_id' => $enrollment->child_id,
                        'name' => $this->generateAdditionalProductInvoiceItemName($additionalProduct, $enrollment),
                        'price' => $additionalPrice,
                        'quantity' => 1,
                        'discount' => 0,
                        'total' => $additionalPrice,
                        'type' => InvoiceItemType::PRODUCT,
                        'paid_amount' => 0,
                        'balance_amount' => $additionalPrice,
                        'paid' => false,
                        'effective_date' => $effectiveDate ?? $forDate,
                    ]);

                    // Create the pivot relationship between enrollment and additional product invoice item
                    $enrollment->invoiceItems()->attach($additionalInvoiceItem->id, [
                        'quantity' => 1,
                        'notes' => $additionalProduct['notes'] ?? 'Additional product',
                    ]);

                    $totalAmount += $additionalPrice;
                }
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
     * Check if an additional product is due for invoicing on the given date.
     *
     * @param array $additionalProduct
     * @param Carbon $forDate
     * @param ChildEnrollment $enrollment
     * @return bool
     */
    protected function isAdditionalProductDueForInvoicing(array $additionalProduct, Carbon $forDate, ChildEnrollment $enrollment): bool
    {
        // Skip one-time billing additional products (they should be handled separately)
        $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
        if ($billedEvery === ChildEnrollmentBilledEvery::ONE_TIME->value) {
            return false;
        }

        // Get start date for additional product or fall back to enrollment start date
        $startDate = isset($additionalProduct['date_start']) 
            ? Carbon::parse($additionalProduct['date_start'])->startOfDay()
            : $enrollment->date_start->startOfDay();
        
        $checkDate = $forDate->copy()->startOfDay();

        // Check if we've passed the start date
        if ($checkDate->lt($startDate)) {
            return false;
        }

        // Check if additional product has ended
        if (isset($additionalProduct['date_end']) && $additionalProduct['date_end']) {
            $endDate = Carbon::parse($additionalProduct['date_end'])->startOfDay();
            if ($checkDate->gt($endDate)) {
                return false;
            }
        }
        
        // Calculate if this date matches the billing cycle
        return $this->matchesAdditionalProductBillingCycle($additionalProduct, $forDate, $startDate);
    }

    /**
     * Check if the given date matches the additional product's billing cycle.
     *
     * @param array $additionalProduct
     * @param Carbon $forDate
     * @param Carbon $startDate
     * @return bool
     */
    protected function matchesAdditionalProductBillingCycle(array $additionalProduct, Carbon $forDate, Carbon $startDate): bool
    {
        $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
        $checkDate = $forDate->copy()->startOfDay();
        
        switch ($billedEvery) {
            case ChildEnrollmentBilledEvery::DAILY->value:
                return true;
                
            case ChildEnrollmentBilledEvery::WEEKLY->value:
                return $checkDate->dayOfWeek === $startDate->dayOfWeek &&
                       $checkDate->diffInWeeks($startDate) >= 0;
                       
            case ChildEnrollmentBilledEvery::MONTHLY->value:
                $targetDay = min($startDate->day, $checkDate->daysInMonth);
                return $checkDate->day === $targetDay &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case ChildEnrollmentBilledEvery::QUARTERLY->value:
                return $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInMonths($startDate) % 3 === 0 &&
                       $checkDate->diffInMonths($startDate) >= 0;
                       
            case ChildEnrollmentBilledEvery::YEARLY->value:
                return $checkDate->month === $startDate->month &&
                       $checkDate->day === min($startDate->day, $checkDate->daysInMonth) &&
                       $checkDate->diffInYears($startDate) >= 0;
                       
            default:
                return false;
        }
    }

    /**
     * Get the price for an additional product.
     *
     * @param array $additionalProduct
     * @param Carbon $forDate
     * @return float
     */
    protected function getAdditionalProductPrice(array $additionalProduct, Carbon $forDate): float
    {
        if (!isset($additionalProduct['product_id'])) {
            return 0;
        }

        $product = \App\Models\Product::find($additionalProduct['product_id']);
        if (!$product) {
            return 0;
        }

        // For now, use the product's base price
        // This could be enhanced to support product pricing tiers, discounts, etc.
        return (float) $product->price;
    }

    /**
     * Generate an invoice item name for an additional product.
     *
     * @param array $additionalProduct
     * @param ChildEnrollment $enrollment
     * @return string
     */
    protected function generateAdditionalProductInvoiceItemName(array $additionalProduct, ChildEnrollment $enrollment): string
    {
        if (!isset($additionalProduct['product_id'])) {
            return 'Additional Product';
        }

        $product = \App\Models\Product::find($additionalProduct['product_id']);
        if (!$product) {
            return 'Additional Product';
        }

        $billingFreq = $additionalProduct['billed_every'] ?? 'monthly';
        $formattedFreq = ucwords(str_replace('_', ' ', $billingFreq));
        
        return "{$product->name} ({$formattedFreq}) - {$enrollment->child->first_name} {$enrollment->child->last_name}";
    }

    /**
     * Generate invoices for one-time billing enrollments and additional products.
     * This can be called manually or when an enrollment is activated.
     *
     * @param Collection|null $enrollments Specific enrollments to process
     * @param Carbon|null $effectiveDate The effective date to use for invoice items (defaults to today)
     * @return array Summary of invoice generation results
     */
    public function generateOneTimeInvoices(?Collection $enrollments = null, ?Carbon $effectiveDate = null): array
    {
        $effectiveDate = $effectiveDate ?? now();
        $results = [
            'total_processed' => 0,
            'invoices_created' => 0,
            'errors' => [],
            'skipped' => [],
            'by_frequency' => []
        ];

        Log::info('Starting one-time invoice generation', ['effective_date' => $effectiveDate->toDateString()]);

        try {
            // Get enrollments if not provided
            if ($enrollments === null) {
                $enrollments = ChildEnrollment::active()
                    ->current()
                    ->where(function ($query) {
                        // Include enrollments with one-time billing OR enrollments with additional one-time products
                        $query->where('billed_every', ChildEnrollmentBilledEvery::ONE_TIME)
                              ->orWhereNotNull('additional_products');
                    })
                    ->with(['child.users', 'centre', 'product'])
                    ->get()
                    ->filter(function ($enrollment) {
                        // Keep enrollments that have one-time billing or have additional one-time products
                        if ($enrollment->billed_every === ChildEnrollmentBilledEvery::ONE_TIME) {
                            return true;
                        }
                        
                        // Check if enrollment has additional products with one-time billing
                        $additionalProducts = $enrollment->additional_products ?? [];
                        foreach ($additionalProducts as $additionalProduct) {
                            $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
                            if ($billedEvery === ChildEnrollmentBilledEvery::ONE_TIME->value) {
                                return true;
                            }
                        }
                        
                        return false;
                    });
            }

            $results['total_processed'] = $enrollments->count();

            Log::info('Found enrollments for one-time processing', ['count' => $enrollments->count()]);

            // Group enrollments by parent/user and centre for consolidated invoices
            $groupedEnrollments = $this->groupEnrollmentsForInvoicing($enrollments);

            foreach ($groupedEnrollments as $groupKey => $group) {
                try {
                    $invoice = $this->createInvoiceForEnrollmentGroup($group, now(), $effectiveDate);
                    if ($invoice) {
                        $results['invoices_created']++;
                        
                        // Track by frequency
                        foreach ($group['enrollments'] as $enrollment) {
                            // Count main enrollment if it's one-time
                            if ($enrollment->billed_every === ChildEnrollmentBilledEvery::ONE_TIME) {
                                $frequency = $enrollment->billed_every->value;
                                $results['by_frequency'][$frequency] = ($results['by_frequency'][$frequency] ?? 0) + 1;
                            }
                            
                            // Count additional one-time products
                            $additionalProducts = $enrollment->additional_products ?? [];
                            foreach ($additionalProducts as $additionalProduct) {
                                $billedEvery = $additionalProduct['billed_every'] ?? 'monthly';
                                if ($billedEvery === ChildEnrollmentBilledEvery::ONE_TIME->value) {
                                    $results['by_frequency'][$billedEvery] = ($results['by_frequency'][$billedEvery] ?? 0) + 1;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create one-time invoice for group', [
                        'group_key' => $groupKey,
                        'error' => $e->getMessage()
                    ]);
                    $results['errors'][] = "Group {$groupKey}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate one-time invoices', ['error' => $e->getMessage()]);
            $results['errors'][] = $e->getMessage();
        }

        Log::info('Completed one-time invoice generation', $results);
        return $results;
    }
}