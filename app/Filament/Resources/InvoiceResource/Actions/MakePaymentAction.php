<?php

namespace App\Filament\Resources\InvoiceResource\Actions;

use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use SyahrinSeth\ChipLaravel\ChipService;
use Chip\Model\Product;

class MakePaymentAction
{
    public static function getDefaultName(): ?string
    {
        return 'make-payment';
    }

    /**
     * Create a table action for making payments
     */
    public static function make(): Action
    {
        return Action::make(static::getDefaultName())
            ->label('Make Payment')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->form(static::getFormSchema())
            ->action(static::getActionCallback());
    }

    /**
     * Create a header action for making payments
     */
    public static function makeHeaderAction(): FilamentAction
    {
        return FilamentAction::make(static::getDefaultName())
            ->label('Make Payment')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->form(static::getFormSchema())
            ->action(static::getActionCallback());
    }

    /**
     * Get the form schema for the payment action
     */
    protected static function getFormSchema(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('gateway')
                        ->label('Payment Gateway')
                        ->options([
                            Gateway::BANK_TRANSFER->value => 'Bank Transfer',
                            Gateway::CHIP->value => 'CHIP',
                        ])
                        ->required()
                        ->live(),

                    TextInput::make('reference_no')
                        ->label('Reference Number')
                        ->required()
                        ->visible(fn (Forms\Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value),
                ]),

            FileUpload::make('payment_proof')
                ->label('Photo')
                ->disk('private')
                ->directory('payment-proofs')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120) // 5MB
                ->helperText('Upload photo. Maximum size: 5MB')
                ->required(fn (Forms\Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value)
                ->visible(fn (Forms\Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value),

            TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->required()
                ->prefix('RM')
                ->step(0.01)
                ->default(function ($livewire) {
                    if (isset($livewire->record) && $livewire->record instanceof Invoice) {
                        return number_format($livewire->record->getRemainingBalance() / 100, 2, '.', '');
                    }
                    return '';
                }),

            DateTimePicker::make('paid_at')
                ->label('Payment Date')
                ->required()
                ->default(now())
                ->displayFormat('M d, Y')
                ->native(false)
                ->visible(fn (Forms\Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value),

            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->columnSpan('full'),
        ];
    }

    /**
     * Get the action callback for the payment action
     */
    protected static function getActionCallback(): \Closure
    {
        return function (array $data, Invoice $record): void {
            if ($data['gateway'] === Gateway::CHIP->value) {
                static::handleChipPayment($data, $record);
            } else {
                static::handleBankTransferPayment($data, $record);
            }
        };
    }

    /**
     * Handle CHIP payment gateway using syahrinseth/chip-laravel
     */
    protected static function handleChipPayment(array $data, Invoice $record): void
    {
        try {
            // Generate unique reference for CHIP payment
            $referenceNo = 'CHIP-' . $record->id . '-' . time();
            
            // Convert amount to cents
            $amountInCents = (int)($data['amount'] * 100);

            // Create payment record first with pending status
            $payment = Payment::create([
                'tenant_id' => $record->tenant_id,
                'centre_id' => $record->centre_id,
                'user_id' => $record->user_id,
                'gateway' => Gateway::CHIP,
                'reference_no' => $referenceNo,
                'status' => PaymentStatus::PENDING,
                'amount' => $amountInCents,
                'description' => $data['description'] ?? null,
                'paid_at' => null,
            ]);

            // Link payment to invoice
            $record->payments()->attach($payment->id, [
                'amount' => $amountInCents,
            ]);

            // Create CHIP product
            $product = new Product();
            $product->name = 'Payment for Invoice #' . $record->invoice_number;
            $product->price = $amountInCents; // in cents

            // Create CHIP payment using the service
            $chipService = new ChipService();
            $purchaseResult = $chipService->createPurchase(
                $record->user->email,
                [$product],
                route('chip.success', ['payment' => $payment->id]),
                route('chip.failure', ['payment' => $payment->id]),
                route('chip.webhook'),
                route('chip.cancel', ['payment' => $payment->id]),
                false, // send_receipt
                $record->user->name
            );

            if ($purchaseResult && isset($purchaseResult->checkout_url)) {
                // Store the CHIP purchase ID for this payment
                $payment->update([
                    'gateway_payment_id' => $purchaseResult->id,
                ]);

                // Redirect to CHIP checkout
                redirect()->away($purchaseResult->checkout_url);
                
            } else {
                // Clean up failed payment
                $payment->delete();
                throw new \Exception('Failed to create CHIP payment session');
            }

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error creating CHIP payment')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Handle bank transfer payment (existing logic)
     */
    protected static function handleBankTransferPayment(array $data, Invoice $record): void
    {
        DB::beginTransaction();
        try {
            // Convert amount from decimal to cents
            $amountInCents = (int) ($data['amount'] * 100);

            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $record->tenant_id,
                'centre_id' => $record->centre_id,
                'user_id' => $record->user_id,
                'gateway' => Gateway::BANK_TRANSFER,
                'reference_no' => $data['reference_no'],
                'status' => PaymentStatus::PAID,
                'amount' => $amountInCents,
                'description' => $data['description'] ?? null,
                'paid_at' => Carbon::parse($data['paid_at']),
            ]);

            // Handle payment proof upload if provided
            if (isset($data['payment_proof']) && !empty($data['payment_proof'])) {
                $filePaths = is_array($data['payment_proof']) ? $data['payment_proof'] : [$data['payment_proof']];
                
                foreach ($filePaths as $filePath) {
                    if ($filePath) {
                        $payment->addMediaFromDisk($filePath, 'private')
                            ->toMediaCollection('payment_proof', 'private');
                    }
                }
            }

            // Link payment to invoice
            $record->payments()->attach($payment->id, [
                'amount' => $amountInCents,
            ]);

            // Update invoice status if full payment was made
            $totalPaid = $record->getTotalPaid() + $amountInCents;
            
            if ($totalPaid >= $record->total) {
                $record->update([
                    'status' => InvoiceStatus::PAID,
                ]);
            }

            DB::commit();

            Notification::make()
                ->title('Payment recorded successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Error recording payment')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}