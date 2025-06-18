<?php

namespace App\Filament\Resources;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('e.g., PROD-001')
                            ->helperText('Unique product code for identification'),
                        
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Monthly Childcare Fee')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Classification')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options(collect(ProductStatus::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()]))
                            ->default(ProductStatus::ACTIVE->value)
                            ->native(false)
                            ->helperText('Current status of the product'),

                        Forms\Components\Select::make('type')
                            ->required()
                            ->options(collect(ProductType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getDisplayName()]))
                            ->default(ProductType::SERVICE->value)
                            ->native(false)
                            ->helperText('Type of product or service'),

                        Forms\Components\Select::make('priority')
                            ->required()
                            ->options([
                                'high' => 'High',
                                'medium' => 'Medium',
                                'low' => 'Low',
                            ])
                            ->default('medium')
                            ->native(false),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Assignment')
                    ->schema([
                        Forms\Components\Select::make('centres')
                            ->label('Centres')
                            ->relationship('centres', 'name', function (Builder $query) {
                                $user = Auth::user();
                                if (!$user->current_tenant_id) {
                                    return $query->whereRaw('1 = 0'); // Return empty query
                                }
                                
                                $query->where('tenant_id', $user->current_tenant_id);
                                
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
                            ->placeholder('Select centres (leave empty for all centres)')
                            ->helperText('Assign this product to specific centres, or leave empty to make it available to all centres'),
                            
                        Forms\Components\Hidden::make('tenant_id')
                            ->default(fn () => Auth::user()?->current_tenant_id),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProductStatus $state): string => match ($state) {
                        ProductStatus::DRAFT => 'gray',
                        ProductStatus::INACTIVE => 'warning',
                        ProductStatus::ACTIVE => 'success',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::SERVICE => 'primary',
                        ProductType::PRODUCT => 'secondary',
                        ProductType::FEE => 'info',
                        ProductType::SUBSCRIPTION => 'warning',
                    })
                    ->formatStateUsing(fn (ProductType $state): string => $state->getDisplayName())
                    ->sortable(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->colors([
                        'danger' => 'high',
                        'warning' => 'medium',
                        'success' => 'low',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('centres.name')
                    ->label('Centres')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('All Centres')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(fn($case) => [$case->value => $case->label()])),

                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn($case) => [$case->value => $case->getDisplayName()])),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                    ]),

                Tables\Filters\SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('All Centres'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn (Product $record) => Auth::user()->can('view', $record)),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Product $record) => Auth::user()->can('update', $record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Product $record) => Auth::user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Product::class)),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
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
