<?php

namespace App\Filament\Admin\Resources\Invoices\RelationManagers;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Invoice Items';

    protected static ?string $modelLabel = 'Item';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                \Filament\Schemas\Components\Section::make()
                    ->schema([
                        \Filament\Schemas\Components\Section::make('Product Selection')
                            ->description('Choose a product or enter custom item details.')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product Template')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query
                                            ->where('tenant_id', Auth::user()?->current_tenant_id)
                                            ->with('currentPrice')
                                    )
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        $label = $record->name;

                                        // Get centre-specific price for the invoice's centre
                                        $invoice = $this->getOwnerRecord();
                                        $centreId = $invoice->centre_id ?? null;
                                        $currentPrice = $record->currentPriceForCentre($centreId);

                                        if ($currentPrice) {
                                            $price = number_format($currentPrice->price / 100, 2);
                                            $label .= " (RM {$price})";
                                        }

                                        return $label;
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('name', $product->name);

                                                // Get centre-specific price for the invoice's centre
                                                $invoice = $this->getOwnerRecord();
                                                $centreId = $invoice->centre_id ?? null;
                                                $currentPrice = $product->currentPriceForCentre($centreId);

                                                // Auto-populate price if product has a current price for this centre
                                                if ($currentPrice) {
                                                    // Convert from cents to decimal for the form
                                                    $price = $currentPrice->price / 100;
                                                    $set('price', number_format($price, 2, '.', ''));

                                                    // Recalculate total after setting the price
                                                    $this->calculateTotal($set, $get);
                                                }
                                            }
                                        }
                                    })
                                    ->helperText('Optional: Select to auto-fill details')
                                    ->columnSpanFull(),

                                TextInput::make('name')
                                    ->label('Item Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Monthly Childcare Fee')
                                    ->helperText('Describe this line item')
                                    ->columnSpanFull(),
                            ]),

                        \Filament\Schemas\Components\Section::make('Pricing Details')
                            ->description('Set quantity, pricing, and discounts.')
                            ->schema([
                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->suffix('unit(s)')
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $this->calculateTotal($set, $get))
                                    ->columnSpan(1),

                                TextInput::make('price')
                                    ->label('Unit Price')
                                    ->required()
                                    ->numeric()
                                    ->prefix('RM')
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        // Convert from cents to decimal for display
                                        $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $this->calculateTotal($set, $get))
                                    ->columnSpan(1),

                                TextInput::make('discount')
                                    ->label('Discount/Unit')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->step(0.01)
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('Per unit, multiplied by quantity')
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        // Convert from cents to decimal for display
                                        $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $this->calculateTotal($set, $get))
                                    ->columnSpan(1),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpan(2),

                \Filament\Schemas\Components\Section::make('Summary & Assignment')
                    ->schema([
                        TextInput::make('total')
                            ->label('Line Total')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->disabled()
                            ->extraAttributes(['class' => 'font-bold text-lg'])
                            ->afterStateHydrated(function (TextInput $component, $state) {
                                // Convert from cents to decimal for display
                                $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                            })
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                            ->helperText('Auto-calculated')
                            ->columnSpanFull(),

                        Select::make('child_id')
                            ->label('Assign to Child')
                            ->relationship(
                                name: 'child',
                                titleAttribute: 'first_name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('tenants', function (Builder $q) {
                                    $q->where('tenant_id', Auth::user()?->current_tenant_id);
                                })
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name.' '.$record->last_name)
                            ->searchable(['first_name', 'last_name'])
                            ->preload()
                            ->placeholder('Optional')
                            ->helperText('Link to specific child')
                            ->columnSpanFull(),

                        DatePicker::make('effective_date')
                            ->label('Effective Date')
                            ->default(now())
                            ->required()
                            ->helperText('Accounting date')
                            ->native(false)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Invoice Items')
            ->description(function () {
                $invoice = $this->getOwnerRecord();
                $totals = $invoice->recalculateTotals();

                return "Items: {$totals['total_items']} | ".
                       'Subtotal: RM '.number_format($totals['total_amount'] / 100, 2).' | '.
                       'Discounts: RM '.number_format($totals['total_discounts'] / 100, 2).' | '.
                       'Total: RM '.number_format($totals['total'] / 100, 2);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('product.code')
                    ->label('Product Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                TextColumn::make('discount')
                    ->label('Discount/Unit')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->toggleable()
                    ->placeholder('RM 0.00'),

                // Tables\Columns\TextColumn::make('*')
                //     ->label('Total Discount')
                //     ->formatStateUsing(fn ($record) => ($record->discount * $record->quantity))
                //     ->money('MYR', divideBy: 100)
                //     ->sortable()
                //     ->toggleable()
                //     ->placeholder('RM 0.00'),

                TextColumn::make('total')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('child.first_name')
                    ->label('Child')
                    ->formatStateUsing(fn ($record) => $record->child ? $record->child->first_name.' '.$record->child->last_name : '-')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Not assigned'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('child')
                    ->relationship('child', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name.' '.$record->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload(),

                Filter::make('has_discount')
                    ->label('Has Discount')
                    ->query(fn (Builder $query): Builder => $query->where('discount', '>', 0)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Ensure the invoice_id is set
                        $data['invoice_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Invoice item created')
                            ->body('Invoice totals have been updated automatically.')
                            ->duration(5000)
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Invoice item updated')
                            ->body('Invoice totals have been updated automatically.')
                            ->duration(5000)
                    ),
                DeleteAction::make()
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Invoice item deleted')
                            ->body('Invoice totals have been updated automatically.')
                            ->duration(5000)
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Invoice items deleted')
                                ->body('Invoice totals have been updated automatically.')
                                ->duration(5000)
                        ),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No invoice items yet')
            ->emptyStateDescription('Add items to this invoice to get started.')
            ->emptyStateIcon('heroicon-o-shopping-cart');
    }

    /**
     * Calculate the total amount based on price, quantity, and discount per unit.
     * Discount is applied per unit and multiplied by quantity.
     */
    private function calculateTotal(Set $set, Get $get): void
    {
        $price = (float) ($get('price') ?? 0);
        $quantity = (int) ($get('quantity') ?? 1);
        $discountPerUnit = (float) ($get('discount') ?? 0);

        $subtotal = $price * $quantity;
        $totalDiscount = $discountPerUnit * $quantity;
        $total = $subtotal - $totalDiscount;

        $set('total', number_format(max(0, $total), 2, '.', ''));
    }
}
