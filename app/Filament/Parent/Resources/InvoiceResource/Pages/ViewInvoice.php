<?php

namespace App\Filament\Parent\Resources\InvoiceResource\Pages;

use App\Enums\Gateway;
use App\Filament\Parent\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('number')
                                        ->label('Invoice Number'),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn ($state): string => match ($state->value) {
                                            'draft' => 'gray',
                                            'pending' => 'warning',
                                            'partially_paid' => 'info',
                                            'paid' => 'success',
                                            'overdue' => 'danger',
                                            'cancelled' => 'gray',
                                            default => 'gray',
                                        }),
                                ]),
                                Group::make([
                                    TextEntry::make('date')
                                        ->label('Billing Month')
                                        ->date('M d, Y'),
                                    TextEntry::make('due_at')
                                        ->label('Due Date')
                                        ->date('M d, Y'),
                                ]),
                            ]),
                        Grid::make(1)
                            ->schema([
                                Group::make([
                                    TextEntry::make('centre.name')
                                        ->label('Centre'),
                                ]),
                            ]),
                    ]),

                Section::make('Invoice Items')
                    ->schema([
                        RepeatableEntry::make('invoiceItems')
                            ->label('')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->columnSpan(1),
                                        TextEntry::make('quantity')
                                            ->label('Quantity')
                                            ->alignCenter(),
                                        TextEntry::make('unit_price')
                                            ->label('Unit Price')
                                            ->money('MYR')
                                            ->formatStateUsing(fn ($state) => $state / 100)
                                            ->alignEnd(),
                                    ]),
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('discount_amount')
                                            ->label('Discount')
                                            ->money('MYR')
                                            ->formatStateUsing(fn ($state) => $state / 100)
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        TextEntry::make('total')
                                            ->label('Line Total')
                                            ->money('MYR')
                                            ->formatStateUsing(fn ($state) => $state / 100)
                                            ->weight('bold')
                                            ->alignEnd(),
                                    ]),
                            ]),
                    ]),

                Section::make('Financial Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('total_amount')
                                        ->label('Subtotal')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('total_discounts')
                                        ->label('Total Discounts')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                ]),
                                Group::make([
                                    TextEntry::make('total')
                                        ->label('Total Amount')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100)
                                        ->weight('bold')
                                        ->size('lg'),
                                    TextEntry::make('total')
                                        ->label('Amount Paid')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state, Invoice $record) => $record->getTotalPaid() / 100)
                                        ->color('success'),
                                    TextEntry::make('total')
                                        ->label('Amount Due')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state, Invoice $record) => $record->getRemainingBalance() / 100)
                                        ->weight('bold')
                                        ->color(fn (Invoice $record): string => $record->getRemainingBalance() > 0 ? 'danger' : 'success')
                                        ->size('lg'),
                                ]),
                            ]),
                    ]),

                Section::make('Payment History')
                    ->visible(fn (Invoice $record) => $record->payments->count() > 0)
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Group::make([
                                            Grid::make(4)
                                                ->schema([
                                                    TextEntry::make('reference_no')
                                                        ->label('Reference'),
                                                    TextEntry::make('gateway')
                                                        ->label('Method')
                                                        ->formatStateUsing(fn (Gateway $state): string => match ($state?->value) {
                                                            'cash' => 'Cash',
                                                            'bank_transfer' => 'Bank Transfer',
                                                            'chip' => 'CHIP',
                                                            default => $state->value,
                                                        }),
                                                    TextEntry::make('status')
                                                        ->label('Status')
                                                        ->badge()
                                                        ->color(fn ($state): string => match ($state->value) {
                                                            'pending' => 'warning',
                                                            'paid' => 'success',
                                                            'failed' => 'danger',
                                                            'cancelled' => 'gray',
                                                            'refunded' => 'info',
                                                            default => 'gray',
                                                        }),
                                                    TextEntry::make('pivot.amount')
                                                        ->label('Amount')
                                                        ->money('MYR')
                                                        ->formatStateUsing(fn ($state) => $state / 100),
                                                ]),
                                            TextEntry::make('paid_at')
                                                ->label('Payment Date')
                                                ->dateTime('M d, Y H:i')
                                                ->placeholder('Not paid yet'),
                                            TextEntry::make('description')
                                                ->label('Description')
                                                ->placeholder('No description')
                                                ->columnSpanFull(),
                                        ]),
                                        Group::make([
                                            SpatieMediaLibraryImageEntry::make('payment_proof')
                                                ->label('Payment Proof')
                                                ->collection('payment_proof'),
                                        ])->visible(fn ($record) => $record->gateway === Gateway::BANK_TRANSFER),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (Invoice $record): string => route('invoice.download-pdf', $record))
                ->openUrlInNewTab()
                ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),

            Action::make('make_payment')
                ->label('Make Payment')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url(fn (Invoice $record): string => route('filament.parent.pages.make-payment', ['invoice' => $record->id]))
                ->visible(fn (Invoice $record) => Auth::user()->can('view', $record) && $record->balance > 0),
        ];
    }
}
