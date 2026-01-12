<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MarkAsPaidAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'mark_as_paid';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Mark as Paid')
            ->color('success')
            ->icon('heroicon-s-check-circle')
            ->requiresConfirmation()
            ->visible(function (Invoice $record): bool {
                // Only visible for pending or overdue invoices
                if ($record->status !== InvoiceStatus::PENDING && $record->status !== InvoiceStatus::OVERDUE) {
                    return false;
                }

                // Check if user has permission to update the invoice
                return Auth::user()->can('update', $record);
            })
            ->action(function (Invoice $record): void {
                $record->status = InvoiceStatus::PAID;
                $record->save();

                Notification::make()
                    ->title('Invoice marked as paid')
                    ->success()
                    ->send();
            });
    }
}
