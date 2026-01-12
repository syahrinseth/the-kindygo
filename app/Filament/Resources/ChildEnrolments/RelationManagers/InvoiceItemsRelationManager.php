<?php

namespace App\Filament\Resources\ChildEnrolments\RelationManagers;

use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $title = 'Items Charged to Parent';

    protected static ?string $label = 'Invoice Item';

    protected static ?string $pluralLabel = 'Invoice Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice Item Details')
                    ->schema([
                        TextInput::make('name')
                            ->label('Item Description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('discount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->default(0)
                            ->columnSpan(1),

                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        Toggle::make('paid')
                            ->label('Paid')
                            ->default(false)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Period Information')
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Period Start')
                            ->columnSpan(1),

                        DatePicker::make('period_end')
                            ->label('Period End')
                            ->columnSpan(1),

                        Textarea::make('description')
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
                TextColumn::make('invoice.number')
                    ->label('Invoice #')
                    ->url(
                        fn ($record): string => route('filament.app.resources.invoices.view', [
                            'tenant' => filament()->getTenant()->id ?? 'default',
                            'record' => $record->invoice_id,
                        ])
                    )
                    ->color('primary')
                    ->weight(FontWeight::Medium)
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Description')
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->wrap(),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('MYR', 100)
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                IconColumn::make('paid')
                    ->label('Paid')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->sortable(),

                TextColumn::make('balance_amount')
                    ->label('Balance')
                    ->money('MYR', 100)
                    ->color(fn ($state): string => $state > 0 ? 'warning' : 'success')
                    ->sortable(),

                TextColumn::make('effective_date')
                    ->label('Effective Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('period_start')
                    ->label('Period Start')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('period_end')
                    ->label('Period End')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('paid')
                    ->label('Payment Status')
                    ->options([
                        1 => 'Paid',
                        0 => 'Unpaid',
                    ]),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('effective_date')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
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
                            $indicators['from'] = 'From '.Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->headerActions([
                // Remove create action as invoice items should be created through the billing process
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_invoice')
                        ->label('View Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->url(
                            fn ($record): string => route('filament.app.resources.invoices.view', [
                                'tenant' => filament()->getTenant(),
                                'record' => $record->invoice_id,
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
            ->toolbarActions([
                // Remove bulk actions for safety
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Invoice Items')
            ->emptyStateDescription('Invoice items will appear here once billing is generated for this enrolment.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public function isReadOnly(): bool
    {
        return true; // Make it read-only since invoice items should be created through billing process
    }
}
