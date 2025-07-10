<?php

namespace App\Filament\Resources\ChildEnrollmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $title = 'Items Charged to Parent';

    protected static ?string $label = 'Invoice Item';

    protected static ?string $pluralLabel = 'Invoice Items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Item Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Item Description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('discount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->default(0)
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('paid')
                            ->label('Paid')
                            ->default(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Period Information')
                    ->schema([
                        Forms\Components\DatePicker::make('period_start')
                            ->label('Period Start')
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('period_end')
                            ->label('Period End')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Additional details about this invoice item')
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.number')
                    ->label('Invoice #')
                    ->url(fn ($record): string => 
                        route('filament.app.resources.invoices.view', [
                            'tenant' => filament()->getTenant()->id ?? 'default',
                            'record' => $record->invoice_id
                        ])
                    )
                    ->color('primary')
                    ->weight(FontWeight::Medium)
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Description')
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->wrap(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR', 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('MYR', 100)
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                Tables\Columns\IconColumn::make('paid')
                    ->label('Paid')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('balance_amount')
                    ->label('Balance')
                    ->money('MYR', 100)
                    ->color(fn ($state): string => $state > 0 ? 'warning' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period Start')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label('Period End')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('paid')
                    ->label('Payment Status')
                    ->options([
                        1 => 'Paid',
                        0 => 'Unpaid',
                    ]),

                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('effective_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('effective_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('effective_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . \Carbon\Carbon::parse($data['until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                // Remove create action as invoice items should be created through the billing process
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_invoice')
                        ->label('View Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->url(fn ($record): string => 
                            route('filament.app.resources.invoices.view', [
                                'tenant' => filament()->getTenant(),
                                'record' => $record->invoice_id
                            ])
                        )
                        ->openUrlInNewTab(),
                ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size('sm')
                ->color('gray')
                ->button(),
            ])
            ->bulkActions([
                // Remove bulk actions for safety
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Invoice Items')
            ->emptyStateDescription('Invoice items will appear here once billing is generated for this enrollment.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public function isReadOnly(): bool
    {
        return true; // Make it read-only since invoice items should be created through billing process
    }
}
