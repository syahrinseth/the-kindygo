<?php

namespace App\Filament\Admin\Resources\Invoices\Actions;

use App\Actions\Payment\MakePaymentAction as MakePaymentActionClass;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Policies\PaymentPolicy;
use Closure;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MakePaymentAction
{
    public static function getDefaultName(): ?string
    {
        return 'make-payment';
    }

    /**
     * Create a table action for making payments
     */
    public static function make(): FilamentAction
    {
        return FilamentAction::make(static::getDefaultName())
            ->label('Make Payment')
            ->icon('heroicon-o-currency-dollar')
            ->color('success')
            ->url(function ($record) {
                $user = Auth::user();

                // If user is a Parent, redirect to MakePayment page with pre-selected invoice
                if ($user && $user->hasRole('parent')) {
                    return '/make-payment?preselect='.$record->id;
                }

                return null; // Return null to show modal for non-parents
            })
            ->openUrlInNewTab(false)
            ->schema(fn ($record) => Auth::user()?->hasRole('parent') ? [] : static::getFormSchema())
            ->action(fn ($data, $record) => Auth::user()?->hasRole('parent') ? null : static::getActionCallback()($data, $record))
            ->visible(function ($record) {
                $user = Auth::user();

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

                if (! $user || ! $user->current_tenant_id) {
                    Log::info('MakePayment Debug: No user or tenant');

                    return false;
                }

                // Check if record is an Invoice instance
                if (! $record instanceof Invoice) {
                    Log::info('MakePayment Debug: Not an invoice record');

                    return false;
                }

                // Super Admin, Admin, Principal can make payments for invoices from their associated centres
                if ($user->hasAnyRole(['super-admin', 'admin', 'principal'])) {
                    // For Super Admin and Admin, check if invoice is from their tenant
                    if ($user->hasAnyRole(['super-admin', 'admin'])) {
                        $result = $record->tenant_id === $user->current_tenant_id;
                        Log::info('MakePayment Debug: Admin/SuperAdmin check', ['result' => $result]);

                        return $result;
                    }

                    // For Principal, check if invoice is from centres they're associated with
                    if ($user->hasRole('principal') && $record->centre_id) {
                        $result = $record->tenant_id === $user->current_tenant_id &&
                               $user->centres()->where('centres.id', $record->centre_id)->exists();
                        Log::info('MakePayment Debug: Principal check', ['result' => $result]);

                        return $result;
                    }
                }

                // Parent and Teacher can only make payments for their own invoices
                if ($user->hasAnyRole(['parent', 'teacher'])) {
                    $result = $record->user_id === $user->id &&
                           $record->tenant_id === $user->current_tenant_id;
                    Log::info('MakePayment Debug: Parent/Teacher check', [
                        'result' => $result,
                        'user_id_match' => $record->user_id === $user->id,
                        'tenant_match' => $record->tenant_id === $user->current_tenant_id,
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
            ->url(function ($livewire) {
                $user = Auth::user();
                $record = $livewire->record ?? null;

                // If user is a Parent, redirect to MakePayment page with pre-selected invoice
                if ($user && $user->hasRole('parent') && $record) {
                    return '/make-payment?preselect='.$record->id;
                }

                return null; // Return null to show modal for non-parents
            })
            ->openUrlInNewTab(false)
            ->schema(fn ($livewire) => Auth::user()?->hasRole('parent') ? [] : static::getFormSchema())
            ->action(fn ($data, $livewire) => Auth::user()?->hasRole('parent') ? null : static::getActionCallback()($data, $livewire->record))
            ->visible(function ($livewire) {
                $user = Auth::user();

                // Get the record from livewire
                $record = $livewire->record ?? null;
                if (! $record instanceof Invoice) {
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

                if (! $user || ! $user->current_tenant_id) {
                    Log::info('MakePayment Debug: No user or tenant');

                    return false;
                }

                // Super Admin, Admin, Principal can make payments for invoices from their associated centres
                if ($user->hasAnyRole(['super-admin', 'admin', 'principal'])) {
                    // For Super Admin and Admin, check if invoice is from their tenant
                    if ($user->hasAnyRole(['super-admin', 'admin'])) {
                        $result = $record->tenant_id === $user->current_tenant_id;
                        Log::info('MakePayment Debug: Admin/SuperAdmin check', ['result' => $result]);

                        return $result;
                    }

                    // For Principal, check if invoice is from centres they're associated with
                    if ($user->hasRole('principal') && $record->centre_id) {
                        $result = $record->tenant_id === $user->current_tenant_id &&
                               $user->centres()->where('centres.id', $record->centre_id)->exists();
                        Log::info('MakePayment Debug: Principal check', ['result' => $result]);

                        return $result;
                    }
                }

                // Parent and Teacher can only make payments for their own invoices
                if ($user->hasAnyRole(['parent', 'teacher'])) {
                    $result = $record->user_id === $user->id &&
                           $record->tenant_id === $user->current_tenant_id;
                    Log::info('MakePayment Debug: Parent/Teacher check', [
                        'result' => $result,
                        'user_id_match' => $record->user_id === $user->id,
                        'tenant_match' => $record->tenant_id === $user->current_tenant_id,
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
                            $user = Auth::user();
                            $policy = new PaymentPolicy;

                            return $policy->getAvailableGateways($user);
                        })
                        ->required()
                        ->live(),

                    TextInput::make('reference_no')
                        ->label('Reference Number')
                        ->required()
                        ->visible(fn (Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value),
                ]),

            FileUpload::make('payment_proof')
                ->label('Photo')
                ->disk('private')
                ->directory('payment-proofs')
                ->image()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120) // 5MB
                ->helperText('Upload photo. Maximum size: 5MB')
                ->required(fn (Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value)
                ->visible(fn (Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value),

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
                ->visible(fn (Get $get): bool => $get('gateway') === Gateway::BANK_TRANSFER->value),

            Textarea::make('description')
                ->label('Description')
                ->columnSpan('full'),
        ];
    }

    /**
     * Get the action callback for the payment action
     */
    protected static function getActionCallback(): Closure
    {
        return function (array $data, Invoice $record): void {
            // Validate gateway authorization
            $user = Auth::user();
            $policy = new PaymentPolicy;

            $gateway = Gateway::from($data['gateway']);

            if ($gateway === Gateway::BANK_TRANSFER && ! $policy->useBankTransferGateway($user)) {
                Notification::make()
                    ->title('Unauthorized Payment Gateway')
                    ->body('You are not authorized to use Bank Transfer gateway.')
                    ->danger()
                    ->send();

                return;
            }

            // Convert amount from decimal to cents
            $totalAmount = (int) ($data['amount'] * 100);

            // Prepare invoice data
            $invoices = [['id' => $record->id]];

            // Prepare additional data
            $additionalData = [];
            if (isset($data['reference_no'])) {
                $additionalData['reference_no'] = $data['reference_no'];
            }
            if (isset($data['payment_proof'])) {
                $additionalData['payment_proof'] = $data['payment_proof'];
            }
            if (isset($data['paid_at'])) {
                $additionalData['paid_at'] = $data['paid_at'];
            }
            if (isset($data['description'])) {
                $additionalData['description'] = $data['description'];
            }

            // Execute payment through unified action
            $makePayment = app(MakePaymentActionClass::class);

            $result = $makePayment->execute(
                user: $user,
                gateway: $gateway,
                totalAmount: $totalAmount,
                invoices: $invoices,
                userAllocation: null,
                additionalData: $additionalData
            );

            // Handle result
            if (! $result->success) {
                Notification::make()
                    ->title('Payment Failed')
                    ->body($result->message)
                    ->danger()
                    ->send();

                return;
            }

            // Handle CHIP redirect
            if ($result->requiresRedirect && $result->checkoutUrl) {
                redirect()->away($result->checkoutUrl);

                return;
            }

            // Success notification for bank transfer
            Notification::make()
                ->title('Payment Recorded Successfully')
                ->body('Payment has been successfully processed.')
                ->success()
                ->send();
        };
    }
}
