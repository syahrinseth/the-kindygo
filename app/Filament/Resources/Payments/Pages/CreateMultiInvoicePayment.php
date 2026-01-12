<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Payment\ProcessMultiInvoicePaymentAction;
use App\Filament\Resources\Payments\Payments\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CreateMultiInvoicePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'Pay Multiple Invoices';

    public function mount(): void
    {
        parent::mount();

        // Pre-fill invoice IDs from session (from bulk action) or URL parameter (from widget)
        $preSelectedIds = session()->pull('multi_payment_invoice_ids');

        // Check URL parameter as fallback
        if (! $preSelectedIds && request()->has('preselect')) {
            $preSelectedIds = explode(',', request()->get('preselect'));
        }

        if ($preSelectedIds) {
            $this->form->fill([
                'invoice_ids' => array_map('intval', $preSelectedIds),
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Select Invoices to Pay')
                    ->description('Select up to 10 invoices. Payment will be allocated to oldest invoices first (FIFO). Partial payment is allowed.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        CheckboxList::make('invoice_ids')
                            ->label('Invoices')
                            ->required()
                            ->options(function () {
                                return Invoice::where('tenant_id', Auth::user()->current_tenant_id)
                                    ->where('user_id', Auth::id())
                                    ->whereIn('status', ['pending', 'overdue'])
                                    ->orderBy('due_at', 'asc')
                                    ->get()
                                    ->filter(fn ($inv) => $inv->getRemainingBalance() > 0)
                                    ->mapWithKeys(function ($invoice) {
                                        $balance = $invoice->getRemainingBalance();
                                        $dueDate = $invoice->due_at->format('d M Y');
                                        $overdue = $invoice->due_at->isPast() ? ' (OVERDUE)' : '';

                                        return [
                                            $invoice->id => "{$invoice->number} - Due: {$dueDate}{$overdue} - Balance: RM ".number_format($balance / 100, 2),
                                        ];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->columns(1)
                            ->gridDirection('row')
                            ->maxItems(10)
                            ->validationMessages([
                                'max' => 'You can only select up to 10 invoices per payment.',
                            ]),

                        Placeholder::make('total_selected')
                            ->label('Total Selected Balance')
                            ->content(function (Get $get) {
                                $invoiceIds = $get('invoice_ids');
                                if (empty($invoiceIds)) {
                                    return 'RM 0.00';
                                }

                                $totalBalance = Invoice::whereIn('id', $invoiceIds)
                                    ->get()
                                    ->sum(fn ($inv) => $inv->getRemainingBalance());

                                return 'RM '.number_format($totalBalance / 100, 2);
                            })
                            ->extraAttributes(['class' => 'text-lg font-bold']),
                    ]),

                Section::make('Payment Details')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        TextInput::make('payment_amount')
                            ->label('Payment Amount (RM)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(0.01)
                            ->prefix('RM')
                            ->helperText('Minimum RM 1.00. Cannot exceed total selected balance.')
                            ->live()
                            ->afterStateUpdated(function (Get $get, $state) {
                                $invoiceIds = $get('invoice_ids');
                                if (! empty($invoiceIds)) {
                                    $totalBalance = Invoice::whereIn('id', $invoiceIds)
                                        ->get()
                                        ->sum(fn ($inv) => $inv->getRemainingBalance());

                                    if (($state * 100) > $totalBalance) {
                                        Notification::make()
                                            ->warning()
                                            ->title('Payment amount exceeds total balance')
                                            ->body('Payment will be adjusted to match the total balance.')
                                            ->send();
                                    }
                                }
                            }),

                        Radio::make('gateway')
                            ->label('Payment Method')
                            ->required()
                            ->options([
                                'bank_transfer' => 'Bank Transfer (Instant)',
                                'chip' => 'Online Payment (CHIP)',
                            ])
                            ->default('bank_transfer')
                            ->live(),

                        TextInput::make('reference_no')
                            ->label('Reference Number')
                            ->required(fn (Get $get) => $get('gateway') === 'bank_transfer')
                            ->visible(fn (Get $get) => $get('gateway') === 'bank_transfer')
                            ->maxLength(255),

                        FileUpload::make('payment_proof')
                            ->label('Payment Proof')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(5120)
                            ->visible(fn (Get $get) => $get('gateway') === 'bank_transfer')
                            ->helperText('Upload proof of payment (JPG, PNG, or PDF, max 5MB)'),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert payment amount from decimal to cents
        $data['payment_amount'] = (int) ($data['payment_amount'] * 100);

        return $data;
    }

    protected function handleRecordCreation(array $data): Payment
    {
        $processPayment = app(ProcessMultiInvoicePaymentAction::class);

        return $processPayment->execute(Auth::user(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $invoiceCount = count($this->data['invoice_ids'] ?? []);

        return Notification::make()
            ->success()
            ->title('Payment Created Successfully')
            ->body("Payment for {$invoiceCount} invoice(s) has been processed.");
    }
}
