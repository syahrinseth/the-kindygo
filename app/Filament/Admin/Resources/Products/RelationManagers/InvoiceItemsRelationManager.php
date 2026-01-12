<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('invoice.number')
                    ->label('Invoice')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                TextColumn::make('discount')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('child.first_name')
                    ->label('Child')
                    ->formatStateUsing(fn ($record) => $record->child ? $record->child->first_name.' '.$record->child->last_name : '-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('invoice')
                    ->relationship('invoice', 'number')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                // Remove create action since invoice items should be created through invoices
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                // Remove bulk actions for safety
            ])
            ->defaultSort('created_at', 'desc');
    }
}
