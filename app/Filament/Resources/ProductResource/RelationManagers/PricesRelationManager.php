<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\ProductPrice;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Schema;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $recordTitleAttribute = 'price';

    protected static ?string $title = 'Price History';

    protected static ?string $modelLabel = 'Price';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price Details')
                    ->schema([
                        TextInput::make('price')
                            ->label('Price')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Enter price in RM (e.g., 100.00)')
                            ->afterStateHydrated(function (TextInput $component, $state) {
                                // Convert from cents to decimal for display
                                $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                            })
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)),
                            
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start_date')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->helperText('When this price becomes effective'),
                                    
                                DatePicker::make('end_date')
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->helperText('Leave empty for open-ended pricing')
                                    ->afterOrEqual('start_date'),
                            ]),
                            
                        Select::make('centres')
                            ->label('Centres')
                            ->relationship('centres', 'name', function (Builder $query) {
                                $user = Auth::user();
                                if (!$user->current_tenant_id) {
                                    return $query->whereRaw('1 = 0');
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
                            ->placeholder('Leave empty for global pricing')
                            ->helperText('Select specific centres for this price, or leave empty to apply to all centres'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR', 100)
                    ->sortable(),
                    
                TextColumn::make('start_date')
                    ->label('Effective From')
                    ->date('M d, Y')
                    ->sortable(),
                    
                TextColumn::make('end_date')
                    ->label('Effective Until')
                    ->date('M d, Y')
                    ->placeholder('Ongoing')
                    ->sortable(),
                    
                TextColumn::make('centres')
                    ->label('Centres')
                    ->getStateUsing(function (ProductPrice $record) {
                        if ($record->centres->count() === 0) {
                            return 'All Centres (Global)';
                        }
                        return $record->centres->pluck('name')->join(', ');
                    })
                    ->wrap()
                    ->sortable(false)
                    ->searchable(false)
                    ->description(function (ProductPrice $record) {
                        if ($record->centres->count() === 0) {
                            return 'Applies to all centres assigned with the product';
                        }
                        $count = $record->centres->count();
                        return $count === 1 ? '1 centre' : "{$count} centres";
                    }),
                    
                TextColumn::make('scope')
                    ->label('Applies To')
                    ->badge()
                    ->getStateUsing(function (ProductPrice $record) {
                        return $record->scope;
                    })
                    ->color(fn (string $state): string => $state === 'Global' ? 'success' : 'primary')
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (ProductPrice $record) {
                        if ($record->isFuture()) {
                            return 'Future';
                        } elseif ($record->isActive()) {
                            return 'Active';
                        } else {
                            return 'Expired';
                        }
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Future' => 'warning',
                        'Expired' => 'gray',
                        default => 'gray',
                    }),
                    
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'future' => 'Future',
                        'expired' => 'Expired',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->current(),
                            'future' => $query->where('start_date', '>', now()->toDateString()),
                            'expired' => $query->where('end_date', '<', now()->toDateString()),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => Auth::user()->can('update', $this->getOwnerRecord())),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (ProductPrice $record) => Auth::user()->can('update', $this->getOwnerRecord())),
                DeleteAction::make()
                    ->visible(fn (ProductPrice $record) => Auth::user()->can('update', $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('update', $this->getOwnerRecord())),
                ]),
            ]);
    }
}
