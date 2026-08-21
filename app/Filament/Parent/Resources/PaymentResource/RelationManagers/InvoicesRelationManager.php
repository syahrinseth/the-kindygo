<?php

namespace App\Filament\Parent\Resources\PaymentResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $title = 'Associated Invoices';

    protected static ?string $modelLabel = 'Invoice';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->label('Invoice Number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
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

                TextColumn::make('total_amount')
                    ->label('Invoice Total')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->sortable(),

                TextColumn::make('pivot.amount')
                    ->label('Payment Amount')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Invoice Date')
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
