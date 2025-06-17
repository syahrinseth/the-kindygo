<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.number')
                    ->label('Invoice')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('child.first_name')
                    ->label('Child')
                    ->formatStateUsing(fn ($record) => $record->child ? $record->child->first_name . ' ' . $record->child->last_name : '-')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('invoice')
                    ->relationship('invoice', 'number')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                // Remove create action since invoice items should be created through invoices
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Remove bulk actions for safety
            ])
            ->defaultSort('created_at', 'desc');
    }
}
