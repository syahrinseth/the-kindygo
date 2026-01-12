<?php

namespace App\Filament\Resources\Quotations\RelationManagers;

use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QuotationItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotationItems';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Quotation Items';

    protected static ?string $modelLabel = 'Item';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->schema([
                        Section::make('Product Selection')
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

                                        $quotation = $this->getOwnerRecord();
                                        $centreId = $quotation->centre_id ?? null;
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

                                                $quotation = $this->getOwnerRecord();
                                                $centreId = $quotation->centre_id ?? null;
                                                $currentPrice = $product->currentPriceForCentre($centreId);

                                                if ($currentPrice) {
                                                    $price = $currentPrice->price / 100;
                                                    $set('price', number_format($price, 2, '.', ''));
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
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Pricing Details')
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
                                        $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                                    ->live()
                                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $this->calculateTotal($set, $get))
                                    ->columnSpan(1),

                                TextInput::make('total')
                                    ->label('Line Total')
                                    ->required()
                                    ->numeric()
                                    ->prefix('RM')
                                    ->disabled()
                                    ->extraAttributes(['class' => 'font-bold text-lg'])
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100))
                                    ->helperText('Auto-calculated')
                                    ->columnSpan(3),
                            ])
                            ->columns(3),

                        Section::make('Period & Description')
                            ->description('Set period dates and additional notes.')
                            ->schema([
                                DatePicker::make('period_start')
                                    ->label('Period Start')
                                    ->native(false),

                                DatePicker::make('period_end')
                                    ->label('Period End')
                                    ->native(false),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(),
                    ])
                    ->columnSpan(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Quotation Items')
            ->description(function () {
                $quotation = $this->getOwnerRecord();

                return "Items: {$quotation->total_items} | ".
                       'Total: RM '.number_format($quotation->total / 100, 2);
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('discount')
                    ->label('Discount')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('MYR', 100)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth('4xl'),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('4xl'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function calculateTotal(Set $set, Get $get): void
    {
        $price = (float) $get('price');
        $quantity = (int) $get('quantity') ?: 1;
        $discount = (float) $get('discount') ?: 0;

        $total = ($price * $quantity) - ($discount * $quantity);
        $set('total', number_format($total, 2, '.', ''));
    }
}
