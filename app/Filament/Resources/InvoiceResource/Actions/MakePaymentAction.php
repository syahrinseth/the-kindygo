<?php

namespace App\Filament\Resources\InvoiceResource\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
            Select::make('gateway')
                ->label('Payment Gateway')
                ->options([
                    'cash' => 'Cash',
                    'bank_transfer' => 'Bank Transfer',
                    'chip' => 'CHIP',
                ])
                ->required(),

            TextInput::make('reference_no')
                ->label('Reference Number')
                ->required(),

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
                ->native(false),

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
            DB::beginTransaction();
            try {
                // Convert amount from decimal to cents
                $amountInCents = (int) ($data['amount'] * 100);

                // Create payment record
                $payment = Payment::create([
                    'tenant_id' => $record->tenant_id,
                    'centre_id' => $record->centre_id,
                    'user_id' => $record->user_id,
                    'gateway' => $data['gateway'],
                    'reference_no' => $data['reference_no'],
                    'status' => PaymentStatus::PAID,
                    'amount' => $amountInCents,
                    'description' => $data['description'] ?? null,
                    'paid_at' => Carbon::parse($data['paid_at']),
                ]);

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
        };
    }
}