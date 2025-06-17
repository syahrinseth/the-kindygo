<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Models\Product;
use App\Models\Child;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\Grid as InfoGrid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'invoiceItems';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Invoice Items';

    protected static ?string $modelLabel = 'Item';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Item Details')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->relationship(
                                name: 'product',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('tenant_id', Auth::user()?->current_tenant_id)
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('name', $product->name);
                                    }
                                }
                            })
                            ->helperText('Select a product to auto-fill the name'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Monthly Childcare Fee')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing & Quantity')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Unit Price')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->step(0.01)
                            ->minValue(0)
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                // Convert from cents to decimal for display
                                $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                            })
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $this->calculateTotal($set, $get)),

                        Forms\Components\TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $this->calculateTotal($set, $get)),

                        Forms\Components\TextInput::make('discount')
                            ->label('Discount Per Unit')
                            ->numeric()
                            ->prefix('RM')
                            ->step(0.01)
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Discount amount per unit (will be multiplied by quantity)')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                // Convert from cents to decimal for display
                                $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                            })
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => 
                                $this->calculateTotal($set, $get)),

                        Forms\Components\TextInput::make('total')
                            ->label('Total Amount')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->disabled()
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                // Convert from cents to decimal for display
                                $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                            })
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Assignment')
                    ->schema([
                        Forms\Components\Select::make('child_id')
                            ->label('Child (Optional)')
                            ->relationship(
                                name: 'child',
                                titleAttribute: 'first_name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('tenants', function (Builder $q) {
                                    $q->where('tenant_id', Auth::user()?->current_tenant_id);
                                })
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                            ->searchable(['first_name', 'last_name'])
                            ->preload()
                            ->helperText('Link this item to a specific child if applicable'),
                    ]),
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
                
                return "Items: {$totals['total_items']} | " .
                       "Subtotal: RM " . number_format($totals['total_amount'] / 100, 2) . " | " .
                       "Discounts: RM " . number_format($totals['total_discounts'] / 100, 2) . " | " .
                       "Total: RM " . number_format($totals['total'] / 100, 2);
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('product.code')
                    ->label('Product Code')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
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

                Tables\Columns\TextColumn::make('total')
                    ->money('MYR', divideBy: 100)
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('child.first_name')
                    ->label('Child')
                    ->formatStateUsing(fn ($record) => $record->child ? $record->child->first_name . ' ' . $record->child->last_name : '-')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Not assigned'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('child')
                    ->relationship('child', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload(),

                Tables\Filters\Filter::make('has_discount')
                    ->label('Has Discount')
                    ->query(fn (Builder $query): Builder => $query->where('discount', '>', 0)),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Ensure the invoice_id is set
                        $data['invoice_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    })
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Invoice item created')
                            ->body('Invoice totals have been updated automatically.')
                            ->duration(5000)
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Invoice item updated')
                            ->body('Invoice totals have been updated automatically.')
                            ->duration(5000)
                    ),
                Tables\Actions\DeleteAction::make()
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Invoice item deleted')
                            ->body('Invoice totals have been updated automatically.')
                            ->duration(5000)
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->successNotification(
                            \Filament\Notifications\Notification::make()
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
    private function calculateTotal(Forms\Set $set, Forms\Get $get): void
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
