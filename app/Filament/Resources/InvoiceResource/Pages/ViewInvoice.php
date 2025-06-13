<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\Gateway;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Actions\MakePaymentAction;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
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
                                            'paid' => 'success',
                                            'overdue' => 'danger',
                                            'cancelled' => 'gray',
                                            default => 'gray',
                                        }),
                                ]),
                                Group::make([
                                    TextEntry::make('date')
                                        ->date('M d, Y'),
                                    TextEntry::make('due_at')
                                        ->date('M d, Y'),
                                ]),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('user.name')
                                        ->label('Client'),
                                ]),
                                Group::make([
                                    TextEntry::make('centre.name')
                                        ->label('Centre'),
                                ]),
                            ]),
                    ]),

                Section::make('Financial Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('total_amount')
                                        ->label('Amount')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('total_discounts')
                                        ->label('Discounts')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                ]),
                                Group::make([
                                    TextEntry::make('total')
                                        ->label('Total Due')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('total')
                                        ->label('Total Paid')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state, Invoice $record) => $record->getTotalPaid() / 100),
                                    TextEntry::make('total')
                                        ->label('Remaining Balance')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state, Invoice $record) => $record->getRemainingBalance() / 100),
                                ]),
                            ]),
                    ]),

                Section::make('Payment History')
                    ->visible(fn (Invoice $record) => $record->payments->count() > 0)
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Group::make([
                                            Grid::make(4)
                                                ->schema([
                                                    TextEntry::make('reference_no')
                                                        ->label('Reference'),
                                                    TextEntry::make('gateway')
                                                        ->label('Gateway')
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
                                            Grid::make(2)
                                                ->schema([
                                                    TextEntry::make('user.name')
                                                        ->label('Paid By'),
                                                    TextEntry::make('paid_at')
                                                        ->label('Payment Date')
                                                        ->date('M d, Y H:i')
                                                        ->placeholder('Not paid yet'),
                                                ]),
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
                            ])
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            MakePaymentAction::makeHeaderAction()
                ->visible(fn (Invoice $record) => 
                    $record->status->value !== 'paid' && 
                    $record->status->value !== 'cancelled'
                ),
            
            Actions\EditAction::make()
                ->visible(fn (Invoice $record) => Auth::user()->can('update', $record)),
        ];
    }
}
