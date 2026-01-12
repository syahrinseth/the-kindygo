<?php

namespace App\Filament\Resources\ChildEnrolments\RelationManagers;

use App\Enums\InvoiceStatus;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Generated Invoices';

    protected static ?string $label = 'Invoice';

    protected static ?string $pluralLabel = 'Invoices';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Details')
                    ->schema([
                        TextInput::make('number')
                            ->label('Invoice Number')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),

                        Select::make('status')
                            ->options(InvoiceStatus::options())
                            ->required()
                            ->columnSpan(1),

                        DateTimePicker::make('date')
                            ->label('Invoice Date')
                            ->required()
                            ->columnSpan(1),

                        DateTimePicker::make('due_at')
                            ->label('Due Date')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('total_amount')
                            ->label('Total Amount')
                            ->numeric()
                            ->prefix('RM')
                            ->step(0.01)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('total')
                            ->label('Total')
                            ->numeric()
                            ->prefix('RM')
                            ->step(0.01)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ucfirst($state))
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Invoice Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('MYR')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('MYR')
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->getStateUsing(function ($record) {
                        // Calculate payment status based on payments
                        $totalPaid = $record->payments()->sum('amount') ?? 0;
                        if ($totalPaid >= $record->total) {
                            return 'paid';
                        } elseif ($totalPaid > 0) {
                            return 'partial';
                        } else {
                            return 'unpaid';
                        }
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ucfirst($state)),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(InvoiceStatus::options())
                    ->multiple(),
            ])
            ->headerActions([
                // Actions\CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
