<?php

namespace App\Filament\Admin\Resources\Products;

use App\Enums\ProductPriority;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Admin\Resources\Products\Pages\CreateProduct;
use App\Filament\Admin\Resources\Products\Pages\EditProduct;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Filament\Admin\Resources\Products\RelationManagers\InvoiceItemsRelationManager;
use App\Filament\Admin\Resources\Products\RelationManagers\PricesRelationManager;
use App\Models\Product;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Products & Services';

    protected static ?string $modelLabel = 'Product & Service';

    protected static ?string $pluralModelLabel = 'Products & Services';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->description('Enter the basic product information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Monthly Childcare Fee')
                            ->columnSpanFull()
                            ->autofocus(),

                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('e.g., PROD-001')
                            ->helperText('Unique product code for identification'),

                        Select::make('priority')
                            ->required()
                            ->options(ProductPriority::getOptions())
                            ->default(ProductPriority::MEDIUM->value)
                            ->native(false)
                            ->helperText('Set the display priority for this product'),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->default(ProductStatus::ACTIVE->value)
                            ->native(false)
                            ->helperText('Set the current status of the product'),
                    ])
                    ->columns(2),

                Section::make('Classification')
                    ->description('Define the product type')
                    ->schema([
                        Select::make('type')
                            ->required()
                            ->options(collect(ProductType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()]))
                            ->default(ProductType::SERVICE->value)
                            ->native(false)
                            ->helperText('Select the type of product or service')
                            ->columnSpanFull(),
                    ]),

                Section::make('Centre Availability')
                    ->description('Control which centres can use this product')
                    ->schema([
                        Select::make('centres')
                            ->label('Available at Centres')
                            ->relationship('centres', 'name', function (Builder $query) {
                                $user = Auth::user();
                                if (! $user->current_tenant_id) {
                                    return $query->whereRaw('1 = 0'); // Return empty query
                                }

                                // If Principal, limit to their centres
                                if ($user->hasRole('Principal')) {
                                    $query->whereHas('users', function (Builder $q) use ($user) {
                                        $q->where('users.id', $user->id);
                                    });
                                }

                                return $query;
                            })
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Leave empty to make available to all centres')
                            ->helperText('Select specific centres, or leave empty to make this product available to all centres in your organisation')
                            ->columnSpanFull(),

                        Hidden::make('tenant_id')
                            ->default(fn () => Auth::user()?->current_tenant_id),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProductStatus $state): string => match ($state) {
                        ProductStatus::DRAFT => 'gray',
                        ProductStatus::INACTIVE => 'warning',
                        ProductStatus::ACTIVE => 'success',
                    })
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (ProductType $state): string => $state->getBadgeColor())
                    ->formatStateUsing(fn (ProductType $state): string => $state->getDisplayName())
                    ->sortable(),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (ProductPriority $state): string => $state->getBadgeColor())
                    ->formatStateUsing(fn (ProductPriority $state): string => $state->getDisplayName())
                    ->sortable(),

                TextColumn::make('centres.name')
                    ->label('Centres')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('All Centres')
                    ->toggleable(),

                TextColumn::make('current_price')
                    ->label('Current Price')
                    ->getStateUsing(function ($record) {
                        $currentPrice = $record->currentPrice;

                        return $currentPrice ? $currentPrice->price : null;
                    })
                    ->money('MYR', 100)
                    ->placeholder('No price set')
                    ->sortable(false),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),

                SelectFilter::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getDisplayName()])),

                SelectFilter::make('priority')
                    ->options(ProductPriority::getOptions()),

                SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('All Centres'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (Product $record) => Auth::user()->can('view', $record)),
                    EditAction::make()
                        ->visible(fn (Product $record) => Auth::user()->can('update', $record)),
                    DeleteAction::make()
                        ->visible(fn (Product $record) => Auth::user()->can('delete', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Product::class)),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            PricesRelationManager::class,
            InvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Auth::user()?->current_tenant_id);
    }

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', Product::class);
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create', Product::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', Product::class);
    }
}
