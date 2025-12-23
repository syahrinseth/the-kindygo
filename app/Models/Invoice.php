<?php

namespace App\Models;

use Exception;
use App\Services\EInvoiceSDKService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Enums\EInvoiceClassificationCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'number',
        'tenant_id',
        'centre_id',
        'user_id',
        'date',
        'due_at',
        'status',
        'total_items',
        'total_discounts',
        'total_amount',
        'total',
        // E-Invoice related fields
        'einvoice_uuid',
        'einvoice_submission_id',
        'einvoice_status',
        'einvoice_validation_url',
        'einvoice_submitted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->number)) {
                $invoice->number = $invoice->generateInvoiceNumber();
            }
        });
    }

    /**
     * Generate a unique invoice number using format: KG{centre_code}/{year}/{running_number}
     *
     * @return string
     */
    public function generateInvoiceNumber(): string
    {
        $date = $this->date ?? now();
        $year = $date->format('Y');

        // Get centre code - use the dedicated code field if available, otherwise fallback to name
        $centre = $this->centre ?? Centre::find($this->centre_id);

        // Generate preschool-friendly centre code
        $centreCode = $this->generatePreschoolCentreCode($centre);

        // Generate sequential number unique to tenant_id, centre_id, and year
        $sequentialNumber = $this->getNextSequentialNumber($year);
        $runningNumber = str_pad($sequentialNumber, 4, '0', STR_PAD_LEFT);

        // Format: KG{centre_code}/{year}/{running_number}
        // KG prefix indicates KindyGo/Kindergarten
        $number = "KG{$centreCode}/{$year}/{$runningNumber}";

        return $number;
    }

    /**
     * Generate a preschool-friendly centre code.
     *
     * @param Centre|null $centre
     * @return string
     */
    private function generatePreschoolCentreCode($centre): string
    {
        if (!$centre) {
            return 'PS01'; // Default preschool code
        }

        // If centre has a dedicated code field, use it
        if (!empty($centre->code)) {
            return strtoupper($centre->code);
        }

        // Generate code from centre name with preschool context
        $name = $centre->name ?? 'Preschool';

        // Common preschool/childcare name patterns
        $preschoolKeywords = [
            'kindergarten' => 'KG',
            'preschool' => 'PS',
            'childcare' => 'CC',
            'nursery' => 'NR',
            'daycare' => 'DC',
            'montessori' => 'MT',
            'academy' => 'AC',
            'learning' => 'LC',
            'centre' => 'CT',
            'center' => 'CT',
            'kids' => 'KD',
            'children' => 'CH',
            'little' => 'LT',
            'tiny' => 'TN',
            'bright' => 'BR',
            'smart' => 'SM',
            'happy' => 'HP',
            'sunny' => 'SN',
            'rainbow' => 'RB',
            'star' => 'ST',
            'golden' => 'GD',
        ];

        $nameLower = strtolower($name);
        $prefix = 'PS'; // Default to Preschool

        // Check for keywords in the name to determine appropriate prefix
        foreach ($preschoolKeywords as $keyword => $code) {
            if (strpos($nameLower, $keyword) !== false) {
                $prefix = $code;
                break;
            }
        }

        // Extract meaningful parts from the name
        $cleanName = preg_replace('/[^A-Za-z0-9\s]/', '', $name);
        $words = explode(' ', $cleanName);

        // Try to create a meaningful suffix from the name
        $suffix = '';
        foreach ($words as $word) {
            $word = strtoupper($word);
            if (strlen($word) >= 2 && !in_array(strtolower($word), array_keys($preschoolKeywords))) {
                $suffix .= substr($word, 0, 2);
                if (strlen($suffix) >= 4) {
                    break;
                }
            }
        }

        // If we couldn't generate a good suffix, use first letters of each word
        if (strlen($suffix) < 2) {
            $suffix = '';
            foreach ($words as $word) {
                if (!empty($word) && !in_array(strtolower($word), array_keys($preschoolKeywords))) {
                    $suffix .= strtoupper($word[0]);
                    if (strlen($suffix) >= 3) {
                        break;
                    }
                }
            }
        }

        // Ensure we have at least 2 characters for suffix
        if (strlen($suffix) < 2) {
            $suffix = '01';
        } else {
            $suffix = substr($suffix, 0, 3); // Limit to 3 characters
        }

        return $prefix . $suffix;
    }

    /**
     * Get the next sequential number for invoice generation.
     * Numbers are unique based on tenant_id, centre_id, and year.
     *
     * @param string $year
     * @return int
     */
    private function getNextSequentialNumber(string $year): int
    {
        $lastInvoice = static::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenant_id)
            ->where('centre_id', $this->centre_id)
            ->whereYear('date', $year)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastInvoice) {
            return 1;
        }

        // Extract number from the last invoice using the new format #{CODE}/{YEAR}/{NUMBER}
        preg_match('/[A-Z0-9]+\/\d{4}\/(\d+)$/', $lastInvoice->number, $matches);

        $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;

        return $lastNumber + 1;
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime',
        'due_at' => 'datetime',
        'status' => InvoiceStatus::class,
        'total_items' => 'integer',
        'total_discounts' => 'integer',
        'total_amount' => 'integer',
        'total' => 'integer',
        'einvoice_submitted_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the invoice.
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the centre that owns the invoice.
     *
     * @return BelongsTo
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * Get the user that owns the invoice.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the child that owns the invoice.
     *
     * @return BelongsTo
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Get all child enrolments related to this invoice through invoice items.
     *
     * @return Collection
     */
    public function getChildEnrolments()
    {
        return ChildEnrolment::whereIn(
            'id',
            $this->invoiceItems()->whereNotNull('child_enrolment_id')->pluck('child_enrolment_id')
        )->get();
    }

    /**
     * Get all children related to this invoice through invoice items.
     *
     * @return Collection
     */
    public function getChildren()
    {
        return Child::whereIn(
            'id',
            $this->invoiceItems()->whereNotNull('child_id')->pluck('child_id')
        )->get();
    }

    /**
     * Check if the invoice is paid.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    /**
     * Check if the invoice is overdue.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::OVERDUE ||
            ($this->status === InvoiceStatus::PENDING && $this->due_at < now());
    }

    /**
     * Scope a query to only include invoices for a specific tenant.
     *
     * @param Builder $query
     * @param  int  $tenantId
     * @return Builder
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include invoices for a specific centre.
     *
     * @param Builder $query
     * @param  int  $centreId
     * @return Builder
     */
    public function scopeForCentre($query, $centreId)
    {
        return $query->where('centre_id', $centreId);
    }

    /**
     * Scope a query to only include overdue invoices.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where(function ($query) {
            $query->where('status', InvoiceStatus::OVERDUE)
                ->orWhere(function ($query) {
                    $query->where('status', InvoiceStatus::PENDING)
                        ->where('due_at', '<', now());
                });
        });
    }

    /**
     * Scope a query to only include invoices with a specific status.
     *
     * @param Builder $query
     * @param InvoiceStatus $status
     * @return Builder
     */
    public function scopeWithStatus($query, InvoiceStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter invoices based on current user's role and permissions.
     *
     * @param Builder $query
     * @param User|null $user
     * @return Builder
     */
    public function scopeForCurrentUser($query)
    {
        $user = Auth::user();


        if (!$user || !$user->current_tenant_id) {
            return $query->whereRaw('1 = 0'); // Return empty result if no user or tenant
        }

        // Filter by current tenant
        $query->where('tenant_id', $user->current_tenant_id);

        // Additional restrictions based on user role
        if ($user->roles && $user->roles->contains('name', 'Parent')) {
            // Parents can only see their own invoices
            $query->where('user_id', $user->id);
        } elseif ($user->roles && $user->roles->contains('name', 'Principal')) {
            // Principals can see invoices from centres they're associated with
            $centreIds = $user->centres()->pluck('id');
            if ($centreIds->isNotEmpty()) {
                $query->whereIn('centre_id', $centreIds);
            } else {
                // If principal has no centres, return empty result
                $query->whereRaw('1 = 0');
            }
        }
        // Super Admin, Admin, and other roles can see all invoices within their tenant

        return $query;
    }

    /**
     * Update the status based on the due date.
     * This is useful for automatically marking invoices as overdue.
     *
     * @return void
     */
    public function updateStatusBasedOnDueDate(): void
    {
        if ($this->status === InvoiceStatus::PENDING && $this->due_at < now()) {
            $this->status = InvoiceStatus::OVERDUE;
            $this->save();
        }
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);

        // When retrieving an invoice, check if it should be marked as overdue
        static::retrieved(function (Invoice $invoice) {
            $invoice->updateStatusBasedOnDueDate();
        });
    }

    /**
     * Format a monetary amount for display.
     *
     * @param int $amount The amount in cents
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public static function formatMoney(int $amount, bool $includeCurrency = true): string
    {
        $formatted = number_format($amount / 100, 2);
        return $includeCurrency ? '$' . $formatted : $formatted;
    }

    /**
     * Get the formatted total amount.
     *
     * @param bool $includeCurrency Whether to include the currency symbol
     * @return string
     */
    public function getFormattedTotal(bool $includeCurrency = true): string
    {
        return self::formatMoney($this->total, $includeCurrency);
    }

    /**
     * Get the payments associated with the invoice.
     *
     * @return BelongsToMany
     */
    public function payments()
    {
        return $this->belongsToMany(Payment::class, 'invoice_payment')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * Get the invoice items associated with the invoice.
     *
     * @return HasMany
     */
    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Alias for invoiceItems relationship for convenience.
     *
     * @return HasMany
     */
    public function items()
    {
        return $this->invoiceItems();
    }

    /**
     * Get all child enrolments associated with this invoice via invoice items.
     *
     * @return HasManyThrough
     */
    public function childEnrolments()
    {
        return $this->hasManyThrough(
            ChildEnrolment::class,
            InvoiceItem::class,
            'invoice_id',
            'id',
            'id',
            'child_enrolment_id'
        )->whereNotNull('invoice_items.child_enrolment_id');
    }

    /**
     * Get all children associated with this invoice via invoice items.
     *
     * @return HasManyThrough
     */
    public function children()
    {
        return $this->hasManyThrough(
            Child::class,
            InvoiceItem::class,
            'invoice_id',
            'id',
            'id',
            'child_id'
        )->whereNotNull('invoice_items.child_id');
    }

    /**
     * Calculate totals based on invoice items and update the invoice.
     * Discount is now applied per unit and multiplied by quantity.
     *
     * @return void
     */
    public function calculateAndUpdateTotals(): void
    {
        $invoiceItems = $this->invoiceItems;

        $totalItems = $invoiceItems->count();
        $totalAmount = $invoiceItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        $totalDiscounts = $invoiceItems->sum(function ($item) {
            return $item->discount * $item->quantity; // Discount per unit * quantity
        });
        $total = $invoiceItems->sum('total');

        // Update the invoice totals
        $this->update([
            'total_items' => $totalItems,
            'total_amount' => $totalAmount, // Before discount amount
            'total_discounts' => $totalDiscounts,
            'total' => $total,
        ]);
    }

    /**
     * Recalculate totals from invoice items.
     * Discount is now applied per unit and multiplied by quantity.
     *
     * @return array
     */
    public function recalculateTotals(): array
    {
        $invoiceItems = $this->invoiceItems;

        $totalItems = $invoiceItems->count();
        $totalAmount = $invoiceItems->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        $totalDiscounts = $invoiceItems->sum(function ($item) {
            return $item->discount * $item->quantity; // Discount per unit * quantity
        });
        $total = $invoiceItems->sum('total');

        return [
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'total_discounts' => $totalDiscounts,
            'total' => $total,
        ];
    }

    /**
     * Get the total amount paid for this invoice (only from 'paid' payments).
     *
     * @return int
     */
    public function getTotalPaid(): int
    {
        return $this->payments()
            ->where('status', PaymentStatus::PAID)
            ->sum('invoice_payment.amount');
    }

    /**
     * Get the remaining balance for this invoice.
     *
     * @return int
     */
    public function getRemainingBalance(): int
    {
        return $this->total - $this->getTotalPaid();
    }

    /**
     * Get the balance attribute (alias for getRemainingBalance for Filament usage).
     *
     * @return int
     */
    public function getBalanceAttribute(): int
    {
        return $this->getRemainingBalance();
    }

    /**
     * Generate a PDF for this invoice.
     *
     * @return string The path to the generated PDF
     */
    public function generatePdf(): string
    {
        // This is a placeholder for PDF generation functionality
        // You would integrate with a PDF library like Dompdf, TCPDF, or Snappy

        // For now, we'll just return a string indicating the feature is not implemented
        return 'PDF generation not implemented yet';
    }

    /**
     * Submit invoice to LHDN e-Invoice system.
     *
     * @return array
     * @throws Exception
     */
    public function submitToEInvoice(): array
    {
        if ($this->einvoice_uuid) {
            throw new Exception('Invoice already submitted to e-Invoice system');
        }

        // Validate that invoice date is not in the future
        if ($this->date > now()) {
            throw new Exception('Invoice date cannot be in the future. Please update the invoice date before submitting to e-Invoice system.');
        }

        // Get tenant's TIN for authentication
        $tenant = $this->tenant;

        // Create e-Invoice service with tenant-specific TIN
        $eInvoiceService = new EInvoiceSDKService($tenant);

        // Convert invoice data to e-Invoice format
        $invoiceData = $this->toEInvoiceFormat();

        // Submit to LHDN
        try {
            $response = $eInvoiceService->submitInvoice($invoiceData);
        } catch (Exception $e) {
            throw new Exception('Failed to submit invoice to e-Invoice system: ' . $e->getMessage());
        }

        // Update invoice with e-Invoice details
        $this->update([
            'einvoice_uuid' => $response['uuid'] ?? null,
            'einvoice_submission_id' => $response['submissionId'] ?? null,
            'einvoice_status' => $response['status'] ?? 'submitted',
            'einvoice_validation_url' => $response['validationUrl'] ?? null,
            'einvoice_submitted_at' => now(),
        ]);

        return $response;
    }

    /**
     * Convert invoice to e-Invoice format (UBL 2.1 format).
     *
     * @return array
     */
    public function toEInvoiceFormat(): array
    {
        return [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [
                'ID' => $this->number,
                'IssueDate' => $this->date->format('Y-m-d'),
                'IssueTime' => $this->date ? $this->date->format('H:i:s\Z') : now()->format('H:i:s\Z'), // Use creation time or current time in UTC format
                'DueDate' => $this->due_at->format('Y-m-d'),
                'InvoiceTypeCode' => [
                    '_' => config('einvoice.invoice_types.standard', '01'),
                    'listVersionID' => '1.0'
                ],
                'DocumentCurrencyCode' => config('einvoice.default_currency', 'MYR'),
                'AccountingSupplierParty' => $this->getSupplierPartyData(),
                'AccountingCustomerParty' => $this->getCustomerPartyData(),
                'InvoiceLine' => $this->getInvoiceLines(),
                'LegalMonetaryTotal' => [
                    'TaxExclusiveAmount' => [
                        '_' => number_format($this->total_amount / 100, 2, '.', ''),
                        'currencyID' => config('einvoice.default_currency', 'MYR')
                    ],
                    'TaxInclusiveAmount' => [
                        '_' => number_format($this->total / 100, 2, '.', ''),
                        'currencyID' => config('einvoice.default_currency', 'MYR')
                    ],
                    'PayableAmount' => [
                        '_' => number_format($this->total / 100, 2, '.', ''),
                        'currencyID' => config('einvoice.default_currency', 'MYR')
                    ]
                ]
            ]
        ];
    }

    /**
     * Get supplier party data for e-Invoice.
     *
     * @return array
     */
    private function getSupplierPartyData(): array
    {
        $centre = $this->centre;
        $tenant = $this->tenant;

        return [
            'Party' => [
                'IndustryClassificationCode' => [
                    'ID' => config('einvoice.supplier_business_activity_code', '85100'),
                    'Description' => config('einvoice.supplier_business_activity_description', 'Child day-care activities')
                ],
                'PartyIdentification' => $this->getTenantPartyIdentification($tenant),
                'PartyName' => [
                    'Name' => $tenant->name
                ],
                'PostalAddress' => [
                    'CityName' => $tenant->city,
                    'PostalZone' => $tenant->postal_code,
                    'CountrySubentityCode' => $tenant->state_code,
                    'AddressLine' => [
                        'Line' => ($tenant->address_1) .
                            ($tenant->address_2 ? ', ' . $tenant->address_2 : '')
                    ],
                    'Country' => [
                        'IdentificationCode' => [
                            '_' => $tenant->country,
                            'listID' => 'ISO3166-1',
                            'listAgencyID' => '6'
                        ]
                    ]
                ],
                'PartyTaxScheme' => [
                    'CompanyID' => [
                        '_' => $tenant->tax_identification_number,
                        'schemeID' => 'TIN'
                    ],
                    'TaxScheme' => [
                        'ID' => [
                            '_' => 'OTH',
                            'schemeID' => 'UN/ECE 5153',
                            'schemeAgencyID' => '6'
                        ]
                    ]
                ],
                'PartyLegalEntity' => [
                    'RegistrationName' => $tenant->name,
                    // 'CompanyID' => [
                    //     '_' => $tenant->business_registration_number,
                    //     'schemeID' => config('einvoice.supplier_registration_number_scheme') // Business Registration Number
                    // ]
                ],
                'Contact' => [
                    'Telephone' => $tenant->phone ?? config('einvoice.supplier_phone', '+60123456789'),
                    'ElectronicMail' => $tenant->email ?? config('einvoice.supplier_email', 'info@kindygo.com')
                ]
            ]
        ];
    }

    /**
     * Get customer party data for e-Invoice.
     *
     * @return array
     */
    private function getCustomerPartyData(): array
    {
        $user = $this->user;

        return [
            'Party' => [
                'PartyIdentification' => [
                    [
                        'ID' => [
                            '_' => $user->getEInvoiceTIN(),
                            'schemeID' => 'TIN'
                        ]
                    ],
                    [
                        'ID' => [
                            '_' => $user->getEInvoiceIdentification(),
                            'schemeID' => $user->getEInvoiceSchemeId()
                        ]
                    ]
                ],
                'PartyName' => [
                    'Name' => $user->name
                ],
                'PostalAddress' => [
                    'CityName' => $user->userAddress?->city ?? config('einvoice.default_city', 'Kuala Lumpur'),
                    'PostalZone' => $user->userAddress?->postal_code ?? config('einvoice.default_postal_code', '50000'),
                    'CountrySubentityCode' => $user->userAddress?->state_code ?? config('einvoice.default_state_code', '14'),
                    'AddressLine' => [
                        'Line' => $user->userAddress?->address ?? 'Address not provided'
                    ],
                    'Country' => [
                        'IdentificationCode' => [
                            '_' => config('einvoice.default_country_code', 'MYS'),
                            'listID' => 'ISO3166-1',
                            'listAgencyID' => '6'
                        ]
                    ]
                ],
                'PartyLegalEntity' => [
                    'RegistrationName' => $user->name
                ],
                'PartyTaxScheme' => [
                    'CompanyID' => [
                        '_' => $user->getEInvoiceIdentification(),
                        'schemeID' => $user->getEInvoiceSchemeId()
                    ],
                    'TaxScheme' => [
                        'ID' => [
                            '_' => 'OTH',
                            'schemeID' => 'UN/ECE 5153',
                            'schemeAgencyID' => '6'
                        ]
                    ]
                ],
                'Contact' => [
                    'Telephone' => $user->profile?->phone ?? $user->phone ?? '+60123456789',
                    'ElectronicMail' => $user->email ?? 'noemail@example.com'
                ]
            ]
        ];
    }

    /**
     * Get invoice lines for e-Invoice.
     * This creates a single line item for childcare services.
     * You can expand this to handle multiple line items if needed.
     *
     * @return array
     */
    private function getInvoiceLines(): array
    {
        return [
            [
                'ID' => '1',
                'InvoicedQuantity' => [
                    '_' => '1',
                    'unitCode' => config('einvoice.unit_codes.service', 'C62')
                ],
                'LineExtensionAmount' => [
                    '_' => number_format($this->total_amount / 100, 2, '.', ''),
                    'currencyID' => config('einvoice.default_currency', 'MYR')
                ],
                'Item' => [
                    'Description' => 'Childcare Services - ' . ($this->centre->name ?? 'KindyGo Services'),
                    'CommodityClassification' => [
                        'ItemClassificationCode' => [
                            '_' => EInvoiceClassificationCode::CHILD_CARE_CENTRES_AND_KINDERGARTENS_FEES->value, // Child day-care activities
                            'listID' => 'CLASS'
                        ]
                    ],
                    'ClassifiedTaxCategory' => [
                        'ID' => config('einvoice.tax_categories.exempt', 'E'), // Exempt from tax
                        'TaxScheme' => [
                            'ID' => [
                                '_' => 'OTH',
                                'schemeID' => 'UN/ECE 5153',
                                'schemeAgencyID' => '6'
                            ]
                        ]
                    ]
                ],
                'Price' => [
                    'PriceAmount' => [
                        '_' => number_format($this->total_amount / 100, 2, '.', ''),
                        'currencyID' => config('einvoice.default_currency', 'MYR')
                    ]
                ],
                'ItemPriceExtension' => [
                    'Amount' => [
                        '_' => number_format($this->total_amount / 100, 2, '.', ''),
                        'currencyID' => config('einvoice.default_currency', 'MYR')
                    ]
                ]
            ]
        ];
    }

    /**
     * Check if invoice is submitted to e-Invoice system.
     *
     * @return bool
     */
    public function isEInvoiceSubmitted(): bool
    {
        return !empty($this->einvoice_uuid);
    }

    /**
     * Get e-Invoice validation URL.
     *
     * @return string|null
     */
    public function getEInvoiceValidationUrl(): ?string
    {
        return $this->einvoice_validation_url;
    }

    /**
     * Get e-Invoice status with human-readable description.
     *
     * @return array
     */
    public function getEInvoiceStatus(): array
    {
        $status = $this->einvoice_status;

        $statusMap = [
            'submitted' => 'Submitted to LHDN',
            'valid' => 'Validated by LHDN',
            'invalid' => 'Rejected by LHDN',
            'cancelled' => 'Cancelled',
            'pending' => 'Pending Validation'
        ];

        return [
            'status' => $status,
            'description' => $statusMap[$status] ?? 'Unknown Status',
            'submitted_at' => $this->einvoice_submitted_at?->format('Y-m-d H:i:s'),
            'validation_url' => $this->einvoice_validation_url
        ];
    }

    /**
     * Cancel the e-Invoice if it was submitted.
     *
     * @param string $reason
     * @return array
     * @throws Exception
     */
    public function cancelEInvoice(string $reason): array
    {
        if (!$this->einvoice_uuid) {
            throw new Exception('Invoice not submitted to e-Invoice system');
        }

        // Get tenant's TIN for authentication
        $tenant = $this->tenant;

        // Create e-Invoice service with tenant-specific TIN
        $eInvoiceService = new EInvoiceSDKService($tenant);

        $response = $eInvoiceService->cancelDocument($this->einvoice_uuid, $reason);

        // Update invoice status
        $this->update([
            'einvoice_status' => 'cancelled'
        ]);

        return $response;
    }

    /**
     * Refresh e-Invoice status from LHDN.
     *
     * @return array
     * @throws Exception
     */
    public function refreshEInvoiceStatus(): array
    {
        if (!$this->einvoice_uuid) {
            throw new Exception('Invoice not submitted to e-Invoice system');
        }

        // Get tenant's TIN for authentication
        $tenant = $this->tenant;

        // Create e-Invoice service with tenant-specific TIN
        $eInvoiceService = new EInvoiceSDKService($tenant);

        $response = $eInvoiceService->getDocumentStatus($this->einvoice_uuid);

        // Update invoice with latest status
        if (isset($response['status'])) {
            $this->update([
                'einvoice_status' => $response['status']
            ]);
        }

        return $response;
    }

    private function getTenantPartyIdentification(?Tenant $tenant = null): array
    {
        $data = [];
        $tenant = $tenant ?? $this->tenant;

        if (!empty($tenant->tax_identification_number)) {
            $data[] = [
                'ID' => [
                    '_' => $tenant->tax_identification_number,
                    'schemeID' => 'TIN'
                ]
            ];
        }

        if (!empty($tenant->business_id_type) && !empty($tenant->business_id_value)) {
            $data[] = [
                'ID' => [
                    '_' => $tenant->business_id_value,
                    'schemeID' => $tenant->business_id_type
                ]
            ];
        }

        return $data;
    }
}
