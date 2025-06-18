<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Payment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('reference_no')
                                        ->label('Reference Number'),
                                    TextEntry::make('gateway')
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
                                        ->dateTime('M d, Y H:i'),
                                    TextEntry::make('gateway_payment_id')
                                        ->label('Gateway Payment ID')
                                        ->placeholder('Not available'),
                                ]),
                            ]),
                    ]),
                    
                Section::make('User & Centre Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('User'),
                                TextEntry::make('centre.name')
                                    ->label('Centre')
                                    ->placeholder('Not assigned'),
                            ]),
                    ]),
                    
                Section::make('Additional Information')
                    ->schema([
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                            
                        SpatieMediaLibraryImageEntry::make('payment_proof')
                            ->label('Payment Proof')
                            ->collection('payment_proof')
                            ->columnSpanFull()
                            ->visibility('private'),
                    ]),
                    
                Section::make('Associated Invoices')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('number')
                                            ->label('Invoice Number'),
                                        TextEntry::make('pivot.amount')
                                            ->label('Payment Amount')
                                            ->money('MYR')
                                            ->formatStateUsing(fn ($state) => $state / 100),
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
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->invoices->count() > 0),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => Auth::user()->can('update', $this->record)),
        ];
    }
}
