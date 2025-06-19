<?php

namespace App\Filament\Resources\CentreResource\RelationManagers;

use App\Enums\InvoiceStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only allow viewing invoices if the user can view the owner record and has appropriate permissions
        return Auth::user()->can('view', $ownerRecord) && 
               Auth::user()->can('viewAny', \App\Models\Invoice::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('date')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('due_at')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),
                    
                Tables\Filters\Filter::make('overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                    
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from'),
                        Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn ($record) => Auth::user()->can('view', $record))
                    ->infolist([
                        Infolists\Components\Section::make('Invoice Details')
                            ->schema([
                                Infolists\Components\TextEntry::make('number')
                                    ->label('Invoice Number')
                                    ->badge()
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                                        InvoiceStatus::DRAFT => 'gray',
                                        InvoiceStatus::PENDING => 'warning',
                                        InvoiceStatus::PAID => 'success',
                                        InvoiceStatus::OVERDUE => 'danger',
                                        InvoiceStatus::CANCELLED => 'gray',
                                    }),
                                    
                                Infolists\Components\TextEntry::make('date')
                                    ->label('Invoice Date')
                                    ->date('M d, Y'),
                                    
                                Infolists\Components\TextEntry::make('due_at')
                                    ->label('Due Date')
                                    ->date('M d, Y')
                                    ->color(function ($record) {
                                        if ($record->due_at && $record->due_at->isPast() && $record->status !== InvoiceStatus::PAID) {
                                            return 'danger';
                                        }
                                        return null;
                                    }),
                            ])->columns(2),
                            
                        Infolists\Components\Section::make('Customer Information')
                            ->schema([
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Customer'),
                                    
                                Infolists\Components\TextEntry::make('user.email')
                                    ->label('Email'),
                                    
                                Infolists\Components\TextEntry::make('centre.name')
                                    ->label('Centre'),
                            ])->columns(2),
                            
                        Infolists\Components\Section::make('Financial Summary')
                            ->schema([
                                Infolists\Components\TextEntry::make('total_items')
                                    ->label('Subtotal')
                                    ->money('MYR'),
                                    
                                Infolists\Components\TextEntry::make('total_discounts')
                                    ->label('Total Discounts')
                                    ->money('MYR')
                                    ->visible(fn ($record) => $record->total_discounts > 0),
                                    
                                Infolists\Components\TextEntry::make('total')
                                    ->label('Total Amount')
                                    ->money('MYR')
                                    ->weight('bold')
                                    ->size('lg'),
                                    
                                Infolists\Components\TextEntry::make('totalPaid')
                                    ->label('Amount Paid')
                                    ->money('MYR')
                                    ->state(fn ($record) => $record->getTotalPaid()),
                                    
                                Infolists\Components\TextEntry::make('remainingBalance')
                                    ->label('Outstanding Balance')
                                    ->money('MYR')
                                    ->state(fn ($record) => $record->getRemainingBalance())
                                    ->color(fn ($record) => $record->getRemainingBalance() > 0 ? 'warning' : 'success'),
                            ])->columns(2),
                            
                        Infolists\Components\Section::make('Invoice Items')
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('invoiceItems')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('name')
                                            ->label('Item'),
                                            
                                        Infolists\Components\TextEntry::make('child.name')
                                            ->label('Child')
                                            ->visible(fn ($state) => $state !== null),
                                            
                                        Infolists\Components\TextEntry::make('quantity')
                                            ->label('Qty'),
                                            
                                        Infolists\Components\TextEntry::make('price')
                                            ->label('Unit Price')
                                            ->money('MYR'),
                                            
                                        Infolists\Components\TextEntry::make('discount')
                                            ->label('Discount')
                                            ->money('MYR')
                                            ->visible(fn ($state) => $state > 0),
                                            
                                        Infolists\Components\TextEntry::make('total')
                                            ->label('Total')
                                            ->money('MYR')
                                            ->weight('semibold'),
                                    ])
                                    ->columns(6)
                                    ->grid(6),
                            ]),
                            
                        Infolists\Components\Section::make('E-Invoice Information')
                            ->schema([
                                Infolists\Components\TextEntry::make('einvoice_status')
                                    ->label('E-Invoice Status')
                                    ->badge()
                                    ->visible(fn ($record) => $record->einvoice_status !== null),
                                    
                                Infolists\Components\TextEntry::make('einvoice_uuid')
                                    ->label('E-Invoice UUID')
                                    ->copyable()
                                    ->visible(fn ($record) => $record->einvoice_uuid !== null),
                                    
                                Infolists\Components\TextEntry::make('einvoice_submitted_at')
                                    ->label('Submitted At')
                                    ->dateTime('M d, Y H:i')
                                    ->visible(fn ($record) => $record->einvoice_submitted_at !== null),
                                    
                                Infolists\Components\TextEntry::make('einvoice_validation_url')
                                    ->label('Validation URL')
                                    ->url(fn ($record) => $record->einvoice_validation_url)
                                    ->openUrlInNewTab()
                                    ->visible(fn ($record) => $record->einvoice_validation_url !== null),
                            ])
                            ->columns(2)
                            ->visible(fn ($record) => $record->isEInvoiceSubmitted()),
                    ]),
            ])
            ->bulkActions([]);
    }
}
