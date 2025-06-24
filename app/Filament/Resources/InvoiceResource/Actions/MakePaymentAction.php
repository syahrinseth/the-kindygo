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
use Illuminate\Support\Facades\Log;
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
            ->action(static::getActionCallback())
            ->visible(function ($record) {
                $user = \Illuminate\Support\Facades\Auth::user();

                // Don't allow payments for cancelled or draft invoices
                if ($record->status === InvoiceStatus::CANCELLED || $record->status === InvoiceStatus::DRAFT || $record->status === InvoiceStatus::PAID) {
                    Log::info('MakePayment Debug: Invoice is cancelled or draft');
                    return false;
                }
                
                // Debug information - you can remove this later
                Log::info('MakePayment Debug - Table Action', [
                    'user_id' => $user?->id,
                    'user_roles' => $user?->getRoleNames(),
                    'current_tenant_id' => $user?->current_tenant_id,
                    'record_type' => get_class($record ?? 'null'),
                    'record_user_id' => $record?->user_id ?? 'null',
                    'record_tenant_id' => $record?->tenant_id ?? 'null',
                ]);
                
                if (!$user || !$user->current_tenant_id) {
                    Log::info('MakePayment Debug: No user or tenant');
                    return false;
                }
                
                // Check if record is an Invoice instance
                if (!$record instanceof \App\Models\Invoice) {
                    Log::info('MakePayment Debug: Not an invoice record');
                    return false;
                }
                
                // Super Admin, Admin, Principal can make payments for invoices from their associated centres
                if ($user->hasAnyRole(['Super Admin', 'Admin', 'Principal'])) {
                    // For Super Admin and Admin, check if invoice is from their tenant
                    if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
                        $result = $record->tenant_id === $user->current_tenant_id;
                        Log::info('MakePayment Debug: Admin/SuperAdmin check', ['result' => $result]);
                        return $result;
                    }
                    
                    // For Principal, check if invoice is from centres they're associated with
                    if ($user->hasRole('Principal') && $record->centre_id) {
                        $result = $record->tenant_id === $user->current_tenant_id &&
                               $user->centres()->where('centres.id', $record->centre_id)->exists();
                        Log::info('MakePayment Debug: Principal check', ['result' => $result]);
                        return $result;
                    }
                }
                
                // Parent and Teacher can only make payments for their own invoices
                if ($user->hasAnyRole(['Parent', 'Teacher'])) {
                    $result = $record->user_id === $user->id && 
                           $record->tenant_id === $user->current_tenant_id;
                    Log::info('MakePayment Debug: Parent/Teacher check', [
                        'result' => $result,
                        'user_id_match' => $record->user_id === $user->id,
                        'tenant_match' => $record->tenant_id === $user->current_tenant_id
                    ]);
                    return $result;
                }
                
                Log::info('MakePayment Debug: No role match');
                return false;
            });
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
            ->action(static::getActionCallback())
            ->visible(function ($livewire) {
                $user = \Illuminate\Support\Facades\Auth::user();
                
                // Get the record from livewire
                $record = $livewire->record ?? null;
                if (!$record instanceof \App\Models\Invoice) {
                    Log::info('MakePayment Debug: Not an invoice record');
                    return false;
                }

                if ($record->status === InvoiceStatus::CANCELLED || $record->status === InvoiceStatus::DRAFT || $record->status === InvoiceStatus::PAID) {
                    Log::info('MakePayment Debug: Invoice is cancelled or draft');
                    return false;
                }
                
                // Debug information - you can remove this later
                Log::info('MakePayment Debug - Header Action', [
                    'user_id' => $user?->id,
                    'user_roles' => $user?->getRoleNames(),
                    'current_tenant_id' => $user?->current_tenant_id,
                    'record_type' => get_class($livewire->record ?? 'null'),
                    'record_user_id' => $livewire->record?->user_id ?? 'null',
                    'record_tenant_id' => $livewire->record?->tenant_id ?? 'null',
                ]);
                
                if (!$user || !$user->current_tenant_id) {
                    Log::info('MakePayment Debug: No user or tenant');
                    return false;
                }
                
                // Super Admin, Admin, Principal can make payments for invoices from their associated centres
                if ($user->hasAnyRole(['Super Admin', 'Admin', 'Principal'])) {
                    // For Super Admin and Admin, check if invoice is from their tenant
                    if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
                        $result = $record->tenant_id === $user->current_tenant_id;
                        Log::info('MakePayment Debug: Admin/SuperAdmin check', ['result' => $result]);
                        return $result;
                    }
                    
                    // For Principal, check if invoice is from centres they're associated with
                    if ($user->hasRole('Principal') && $record->centre_id) {
                        $result = $record->tenant_id === $user->current_tenant_id &&
                               $user->centres()->where('centres.id', $record->centre_id)->exists();
                        Log::info('MakePayment Debug: Principal check', ['result' => $result]);
                        return $result;
                    }
                }
                
                // Parent and Teacher can only make payments for their own invoices
                if ($user->hasAnyRole(['Parent', 'Teacher'])) {
                    $result = $record->user_id === $user->id && 
                           $record->tenant_id === $user->current_tenant_id;
                    Log::info('MakePayment Debug: Parent/Teacher check', [
                        'result' => $result,
                        'user_id_match' => $record->user_id === $user->id,
                        'tenant_match' => $record->tenant_id === $user->current_tenant_id
                    ]);
                    return $result;
                }
                
                Log::info('MakePayment Debug: No role match');
                return false;
            });
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
                        ->options(function () {
                            $user = \Illuminate\Support\Facades\Auth::user();
                            $policy = new \App\Policies\PaymentPolicy();
                            return $policy->getAvailableGateways($user);
                        })
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
            // Validate gateway authorization
            $user = \Illuminate\Support\Facades\Auth::user();
            $policy = new \App\Policies\PaymentPolicy();
            
            if ($data['gateway'] === Gateway::BANK_TRANSFER->value && !$policy->useBankTransferGateway($user)) {
                \Filament\Notifications\Notification::make()
                    ->title('Unauthorized Payment Gateway')
                    ->body('You are not authorized to use Bank Transfer gateway.')
                    ->danger()
                    ->send();
                return;
            }
            
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
                // Store the CHIP purchase ID and comprehensive payment data
                $payment->update([
                    'gateway_payment_id' => $purchaseResult->id,
                    'gateway_payment_data' => [
                        // Main chip_data structure with comprehensive information
                        'chip_data' => [
                            'id' => $purchaseResult->id,
                            'status' => $purchaseResult->status ?? 'pending',
                            'payment_method' => $purchaseResult->transaction_data?->payment_method ?? 
                                              $purchaseResult->payment_method ?? null,
                            'checkout_url' => $purchaseResult->checkout_url,
                            'created_on' => $purchaseResult->created_on ?? now()->toISOString(),
                            'updated_on' => $purchaseResult->updated_on ?? now()->toISOString(),
                            'brand_id' => $purchaseResult->brand_id ?? null,
                            'currency' => $purchaseResult->purchase?->currency ?? 'MYR',
                            'total' => $purchaseResult->purchase?->total ?? $amountInCents,
                            'client_email' => $record->user->email,
                            'client_name' => $record->user->name,
                            'reference' => $referenceNo,
                        ],
                        // Legacy support - keep some root level data for backward compatibility
                        'id' => $purchaseResult->id,
                        'status' => $purchaseResult->status ?? 'pending',
                        'checkout_url' => $purchaseResult->checkout_url,
                        'payment_method' => $purchaseResult->transaction_data?->payment_method ?? 
                                          $purchaseResult->payment_method ?? null,
                        'created_on' => $purchaseResult->created_on ?? now()->toISOString(),
                        'updated_on' => $purchaseResult->updated_on ?? now()->toISOString(),
                        'brand_id' => $purchaseResult->brand_id ?? null,
                        'client' => [
                            'email' => $record->user->email,
                            'full_name' => $record->user->name,
                        ],
                        'purchase' => [
                            'total' => $purchaseResult->purchase?->total ?? $amountInCents,
                            'currency' => $purchaseResult->purchase?->currency ?? 'MYR',
                            'products' => $purchaseResult->purchase?->products ?? [],
                        ],
                        'stored_at' => now()->toISOString(),
                    ]
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