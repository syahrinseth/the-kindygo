<?php

namespace App\Filament\Parent\Pages;

use App\Actions\Payment\MakePaymentAction;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Models\Centre;
use App\Models\Invoice;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MakePayment extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $title = 'Make Payment';

    protected static ?string $navigationLabel = 'Make Payment';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.parent.pages.make-payment';

    /** @var array<int, bool> */
    public array $selectedInvoices = [];

    /** @var array<int, float> */
    public array $selectedAmounts = [];

    public int $totalAmount = 0;

    public function mount(): void
    {
        $this->loadUnpaidInvoices();

        // Support pre-selection from URL parameter
        if (request()->has('preselect')) {
            $this->handlePreselection();
        }
    }

    protected function loadUnpaidInvoices(): void
    {
        $user = Auth::user();

        $invoices = Invoice::where('user_id', $user->id)
            ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE])
            ->where('total', '>', 0)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingBalance() > 0);

        // Initialize: check all invoices and pre-fill with full balance
        foreach ($invoices as $invoice) {
            $this->selectedInvoices[$invoice->id] = true;
            $this->selectedAmounts[$invoice->id] = $invoice->getRemainingBalance() / 100;
        }

        $this->calculateTotal();
    }

    protected function handlePreselection(): void
    {
        // First, uncheck all invoices
        foreach ($this->selectedInvoices as $id => $selected) {
            $this->selectedInvoices[$id] = false;
            $this->selectedAmounts[$id] = 0;
        }

        // Then, check and pre-fill only the specified invoices
        $preselectedIds = array_filter(
            array_map('intval', explode(',', request()->get('preselect')))
        );

        $user = Auth::user();

        foreach ($preselectedIds as $invoiceId) {
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->first();

            if ($invoice && $invoice->getRemainingBalance() > 0) {
                $this->selectedInvoices[$invoice->id] = true;
                $this->selectedAmounts[$invoice->id] = $invoice->getRemainingBalance() / 100;
            }
        }

        $this->calculateTotal();
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(
                Invoice::query()
                    ->where('user_id', $user?->id)
                    ->whereIn('status', [
                        InvoiceStatus::PENDING,
                        InvoiceStatus::PARTIALLY_PAID,
                        InvoiceStatus::OVERDUE,
                    ])
                    ->where('total', '>', 0)
                    ->with(['centre'])
            )
            ->columns([
                TextColumn::make('number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('centre.name')
                    ->label('Centre')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_at')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn (Invoice $record): int => $record->getRemainingBalance())
                    ->money('MYR', divideBy: 100)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::PARTIALLY_PAID => 'info',
                        InvoiceStatus::PAID => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        InvoiceStatus::PENDING->value => 'Pending',
                        InvoiceStatus::OVERDUE->value => 'Overdue',
                        InvoiceStatus::PARTIALLY_PAID->value => 'Partially Paid',
                    ])
                    ->multiple()
                    ->label('Status'),
                SelectFilter::make('centre_id')
                    ->label('Centre')
                    ->options(function () {
                        $user = Auth::user();

                        return Centre::whereHas('invoices', function (Builder $query) use ($user) {
                            $query->where('user_id', $user?->id);
                        })->pluck('name', 'id')->toArray();
                    })
                    ->multiple(),
            ])
            ->deferFilters(false)
            ->defaultSort('due_at', 'asc')
            ->paginated(false)
            ->poll('30s');
    }

    public function toggleInvoice(int $invoiceId): void
    {
        $user = Auth::user();
        $invoice = Invoice::where('id', $invoiceId)
            ->where('user_id', $user->id)
            ->first();

        if (! $invoice) {
            return;
        }

        // Toggle selection
        $this->selectedInvoices[$invoiceId] = ! ($this->selectedInvoices[$invoiceId] ?? false);

        // If unchecked, clear the amount; if checked, pre-fill with full balance
        if (! $this->selectedInvoices[$invoiceId]) {
            $this->selectedAmounts[$invoiceId] = 0;
        } else {
            $this->selectedAmounts[$invoiceId] = $invoice->getRemainingBalance() / 100;
        }

        $this->calculateTotal();
    }

    public function updateAmount(int $invoiceId, float $amount): void
    {
        $user = Auth::user();
        $invoice = Invoice::where('id', $invoiceId)
            ->where('user_id', $user->id)
            ->first();

        if ($invoice) {
            $maxAmount = $invoice->getRemainingBalance() / 100;
            $this->selectedAmounts[$invoiceId] = min(max(0, $amount), $maxAmount);
            $this->calculateTotal();
        }
    }

    public function calculateTotal(): void
    {
        $total = 0;

        foreach ($this->selectedAmounts as $invoiceId => $amount) {
            if (($this->selectedInvoices[$invoiceId] ?? false) && $amount > 0) {
                $total += $amount;
            }
        }

        $this->totalAmount = (int) round($total * 100);
    }

    public function selectAll(): void
    {
        $user = Auth::user();

        $invoices = Invoice::where('user_id', $user->id)
            ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE])
            ->where('total', '>', 0)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingBalance() > 0);

        foreach ($invoices as $invoice) {
            $this->selectedInvoices[$invoice->id] = true;
            $this->selectedAmounts[$invoice->id] = $invoice->getRemainingBalance() / 100;
        }

        $this->calculateTotal();
    }

    public function deselectAll(): void
    {
        foreach ($this->selectedInvoices as $id => $selected) {
            $this->selectedInvoices[$id] = false;
            $this->selectedAmounts[$id] = 0;
        }

        $this->totalAmount = 0;
    }

    /**
     * Get unique centre IDs from selected invoices.
     *
     * @return array<int>
     */
    public function getSelectedCentres(): array
    {
        $user = Auth::user();
        $centreIds = [];

        $invoices = Invoice::whereIn('id', array_keys(array_filter($this->selectedInvoices)))
            ->where('user_id', $user->id)
            ->get();

        foreach ($invoices as $invoice) {
            if (($this->selectedAmounts[$invoice->id] ?? 0) > 0 && ! empty($invoice->centre_id)) {
                $centreIds[] = $invoice->centre_id;
            }
        }

        return array_values(array_unique($centreIds));
    }

    /**
     * Check if selection spans multiple centres.
     */
    public function isMultiCentreSelection(): bool
    {
        return count($this->getSelectedCentres()) > 1;
    }

    /**
     * Calculate total per centre.
     *
     * @return array<array{centre_id: int|string, centre_name: string, total: float}>
     */
    public function getCentreTotals(): array
    {
        $user = Auth::user();
        $centreTotals = [];

        $invoices = Invoice::whereIn('id', array_keys(array_filter($this->selectedInvoices)))
            ->where('user_id', $user->id)
            ->with('centre')
            ->get();

        foreach ($invoices as $invoice) {
            $amount = $this->selectedAmounts[$invoice->id] ?? 0;

            if ($amount > 0) {
                $centreId = $invoice->centre_id ?? 'unassigned';

                if (! isset($centreTotals[$centreId])) {
                    $centreTotals[$centreId] = [
                        'centre_id' => $centreId,
                        'centre_name' => $invoice->centre?->name ?? 'Unassigned',
                        'total' => 0,
                    ];
                }

                $centreTotals[$centreId]['total'] += $amount;
            }
        }

        return array_values($centreTotals);
    }

    /**
     * Get invoices grouped by centre for UI display.
     *
     * @return array<array{centre_id: int|string, centre_name: string, invoices: array}>
     */
    public function getInvoicesByCentre(): array
    {
        $user = Auth::user();

        $invoices = Invoice::where('user_id', $user->id)
            ->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE])
            ->where('total', '>', 0)
            ->with(['centre', 'invoiceItems.product'])
            ->orderBy('due_at')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->getRemainingBalance() > 0);

        $grouped = [];

        foreach ($invoices as $invoice) {
            $centreId = $invoice->centre_id ?? 'unassigned';
            $centreName = $invoice->centre?->name ?? 'Unassigned';

            if (! isset($grouped[$centreId])) {
                $grouped[$centreId] = [
                    'centre_id' => $centreId,
                    'centre_name' => $centreName,
                    'invoices' => [],
                ];
            }

            $grouped[$centreId]['invoices'][] = [
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
                $allocation[$invoiceId] = (int) ($amount * 100);
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('proceedToPayment')
                ->label('Proceed to Payment')
                ->color('primary')
                ->size('lg')
                ->icon('heroicon-o-credit-card')
                ->disabled(fn (): bool => $this->totalAmount <= 0)
                ->action('processPayment'),
        ];
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasRole('Parent');
    }
}
