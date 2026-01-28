<?php

namespace App\Filament\Parent\Pages;

use App\Actions\Payment\MakePaymentAction;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MakePayment extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $title = 'Make Payment';

    protected static ?string $navigationLabel = 'Make Payment';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.parent.pages.make-payment';

    public array $invoices = [];

    public array $selectedInvoices = [];

    public array $selectedAmounts = [];

    public int $totalAmount = 0;

    public function mount(): void
    {
        $this->loadUnpaidInvoices();

        // Support pre-selection from URL parameter
        if (request()->has('preselect')) {
            // First, uncheck all invoices
            foreach ($this->invoices as $invoice) {
                $this->selectedInvoices[$invoice['id']] = false;
                $this->selectedAmounts[$invoice['id']] = 0;
            }

            // Then, check and pre-fill only the specified invoices
            $preselectedIds = array_filter(
                array_map('intval', explode(',', request()->get('preselect')))
            );

            foreach ($preselectedIds as $invoiceId) {
                $invoice = collect($this->invoices)->firstWhere('id', $invoiceId);
                if ($invoice && $invoice['balance'] > 0) {
                    $this->selectedInvoices[$invoice['id']] = true;
                    $this->selectedAmounts[$invoice['id']] = $invoice['balance'] / 100;
                }
            }

            $this->calculateTotal();
        }
    }

    protected function loadUnpaidInvoices(): void
    {
        $user = Auth::user();

        $this->invoices = Invoice::where('user_id', $user->id)
            ->whereIn('status', [InvoiceStatus::DRAFT, InvoiceStatus::PENDING, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE])
            ->where('total', '>', 0)
            ->with(['centre', 'invoiceItems.product'])
            ->orderBy('due_at')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingBalance() > 0)
            ->map(function (Invoice $invoice) {
                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'date' => $invoice->date,
                    'due_at' => $invoice->due_at,
                    'total' => $invoice->total,
                    'balance' => $invoice->getRemainingBalance(),
                    'centre_id' => $invoice->centre_id,
                    'centre_name' => $invoice->centre?->name,
                    'items' => $invoice->invoiceItems->map(fn ($item) => [
                        'description' => $item->description ?? $item->name,
                        'total' => $item->total,
                        'balance' => $item->balance_amount,
                        'priority' => $item->product?->priority?->value ?? 2,
                    ])->toArray(),
                ];
            })
            ->values()
            ->toArray();

        // Initialize selected amounts with maximum balance for each invoice
        // By default, check all invoices and pre-fill with full balance
        foreach ($this->invoices as $invoice) {
            $this->selectedInvoices[$invoice['id']] = true; // Check by default
            $this->selectedAmounts[$invoice['id']] = $invoice['balance'] / 100; // Full balance in dollars
        }

        // Calculate initial total
        $this->calculateTotal();
    }

    public function toggleInvoice($invoiceId): void
    {
        $invoice = collect($this->invoices)->firstWhere('id', $invoiceId);

        if (! $invoice) {
            return;
        }

        // Toggle selection
        $this->selectedInvoices[$invoiceId] = ! ($this->selectedInvoices[$invoiceId] ?? false);

        // If unchecked, clear the amount
        if (! $this->selectedInvoices[$invoiceId]) {
            $this->selectedAmounts[$invoiceId] = 0;
        } else {
            // If checked, pre-fill with full balance
            $this->selectedAmounts[$invoiceId] = $invoice['balance'] / 100;
        }

        $this->calculateTotal();
    }

    public function updateAmount($invoiceId, $amount): void
    {
        $amount = floatval($amount);
        $invoice = collect($this->invoices)->firstWhere('id', $invoiceId);

        if ($invoice) {
            $maxAmount = $invoice['balance'] / 100;
            $this->selectedAmounts[$invoiceId] = min($amount, $maxAmount);
            $this->calculateTotal();
        }
    }

    public function calculateTotal(): void
    {
        $total = 0;

        foreach ($this->selectedAmounts as $invoiceId => $amount) {
            // Only count if invoice is selected
            if (($this->selectedInvoices[$invoiceId] ?? false) && $amount > 0) {
                $total += $amount;
            }
        }

        $this->totalAmount = (int) ($total * 100); // Convert to cents
    }

    /**
     * Get unique centre IDs from selected invoices
     */
    public function getSelectedCentres(): array
    {
        $centreIds = [];

        foreach ($this->invoices as $invoice) {
            if (($this->selectedInvoices[$invoice['id']] ?? false) &&
                ($this->selectedAmounts[$invoice['id']] ?? 0) > 0 &&
                ! empty($invoice['centre_id'])) {
                $centreIds[] = $invoice['centre_id'];
            }
        }

        return array_values(array_unique($centreIds));
    }

    /**
     * Check if selection spans multiple centres
     */
    public function isMultiCentreSelection(): bool
    {
        return count($this->getSelectedCentres()) > 1;
    }

    /**
     * Calculate total per centre
     */
    public function getCentreTotals(): array
    {
        $centreTotals = [];

        foreach ($this->invoices as $invoice) {
            if (($this->selectedInvoices[$invoice['id']] ?? false) &&
                ($this->selectedAmounts[$invoice['id']] ?? 0) > 0) {
                $centreId = $invoice['centre_id'] ?? 'unassigned';
                $amount = $this->selectedAmounts[$invoice['id']] ?? 0;

                if (! isset($centreTotals[$centreId])) {
                    $centreTotals[$centreId] = [
                        'centre_id' => $centreId,
                        'centre_name' => $invoice['centre_name'] ?? 'Unassigned',
                        'total' => 0,
                    ];
                }

                $centreTotals[$centreId]['total'] += $amount;
            }
        }

        return array_values($centreTotals);
    }

    /**
     * Group invoices by centre for UI display
     */
    public function getInvoicesByCentre(): array
    {
        $grouped = [];

        foreach ($this->invoices as $invoice) {
            $centreId = $invoice['centre_id'] ?? 'unassigned';
            $centreName = $invoice['centre_name'] ?? 'Unassigned';

            if (! isset($grouped[$centreId])) {
                $grouped[$centreId] = [
                    'centre_id' => $centreId,
                    'centre_name' => $centreName,
                    'invoices' => [],
                ];
            }

            $grouped[$centreId]['invoices'][] = $invoice;
        }

        return array_values($grouped);
    }

    public function processPayment(): void
    {
        $this->calculateTotal();

        if ($this->totalAmount <= 0) {
            Notification::make()
                ->danger()
                ->title('Invalid Payment Amount')
                ->body('Please select at least one invoice and enter a valid payment amount.')
                ->send();

            return;
        }

        // Prepare allocation data (only include selected invoices with amount > 0)
        $allocation = [];
        foreach ($this->selectedAmounts as $invoiceId => $amount) {
            if (($this->selectedInvoices[$invoiceId] ?? false) && $amount > 0) {
                $allocation[$invoiceId] = (int) ($amount * 100); // Convert to cents
            }
        }

        if (empty($allocation)) {
            Notification::make()
                ->danger()
                ->title('No Invoices Selected')
                ->body('Please enter an amount for at least one invoice.')
                ->send();

            return;
        }

        try {
            // Transform allocation data to invoice array format
            $invoices = array_map(
                fn ($invoiceId) => ['id' => $invoiceId],
                array_keys($allocation)
            );

            // Execute payment through MakePaymentAction (gateway pattern)
            $makePayment = app(MakePaymentAction::class);
            $result = $makePayment->execute(
                user: Auth::user(),
                gateway: Gateway::CHIP,
                totalAmount: $this->totalAmount,
                invoices: $invoices,
                userAllocation: $allocation,
                additionalData: []
            );

            // Handle failure
            if (! $result->success) {
                Notification::make()
                    ->danger()
                    ->title('Payment Failed')
                    ->body($result->message)
                    ->send();

                return;
            }

            // Redirect to CHIP checkout
            if ($result->requiresRedirect && $result->checkoutUrl) {
                $this->redirect($result->checkoutUrl);
            }

        } catch (Exception $e) {
            Log::error('Failed to create CHIP payment', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'total_amount' => $this->totalAmount,
                'allocation' => $allocation,
            ]);

            Notification::make()
                ->danger()
                ->title('Payment Failed')
                ->body('Unable to process payment. Please try again or contact support.')
                ->send();
        }
    }

    protected function getActions(): array
    {
        return [
            Action::make('proceedToPayment')
                ->label('Proceed to Payment')
                ->color('primary')
                ->size('lg')
                ->disabled(fn (): bool => $this->totalAmount <= 0)
                ->action('processPayment'),
        ];
    }

    public static function canAccess(): bool
    {
        // Only allow Parent role to access
        return Auth::check() && Auth::user()->hasRole('Parent');
    }
}
