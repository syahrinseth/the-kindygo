<?php

namespace App\Filament\Parent\Resources\PaymentResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Parent\Resources\PaymentResource;
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

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('reference_no')
                                        ->label('Reference Number'),
                                    TextEntry::make('gateway')
                                        ->label('Payment Method')
                                        ->badge()
                                        ->color(fn ($state): string => match ($state->value) {
                                            'bank_transfer' => 'blue',
                                            'chip' => 'green',
                                            'cash' => 'gray',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn ($state): string => match ($state->value) {
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'cancelled' => 'gray',
                                            'refunded' => 'info',
                                            default => 'gray',
                                        }),
                                ]),
                                Group::make([
                                    TextEntry::make('amount')
                                        ->label('Amount')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('paid_at')
                                        ->label('Payment Date')
                                        ->dateTime('M d, Y H:i')
                                        ->placeholder('Pending'),
                                    TextEntry::make('centres.name')
                                        ->label('Centre(s)')
                                        ->badge()
                                        ->separator(', ')
                                        ->placeholder('Not specified'),
                                ]),
                            ]),
                    ]),

                Section::make('Invoice Details')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->label('')
                            ->schema([
                                TextEntry::make('number')
                                    ->label('Invoice Number'),
                                TextEntry::make('total')
                                    ->label('Invoice Total')
                                    ->money('MYR')
                                    ->formatStateUsing(fn ($state) => $state / 100),
                                TextEntry::make('pivot.amount')
                                    ->label('Payment Applied')
                                    ->money('MYR')
                                    ->formatStateUsing(fn ($state) => $state / 100),
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
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn ($record) => $record->invoices->isNotEmpty()),

                Section::make('Payment Proof')
                    ->schema([
                        SpatieMediaLibraryImageEntry::make('payment_proof')
                            ->label('')
                            ->collection('payment_proof')
                            ->disk('private')
                            ->visible(fn ($record) => $record->hasMedia('payment_proof')),
                        TextEntry::make('description')
                            ->label('Notes')
                            ->placeholder('No notes provided')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->hasMedia('payment_proof') || $record->description),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_receipt')
                ->label('Download Receipt')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn () => route('payments.receipt.download', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => Auth::user()->can('view', $this->record) && $this->record->status === PaymentStatus::PAID),
        ];
    }
}
