<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductPrice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $recordTitleAttribute = 'price';

    protected static ?string $title = 'Price History';

    protected static ?string $modelLabel = 'Price';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Price Details')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->step(0.01)
                            ->minValue(0)
                            ->helperText('Enter price in RM (e.g., 100.00)')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                // Convert from cents to decimal for display
                                $component->state($state ? number_format($state / 100, 2, '.', '') : '0.00');
                            })
                            ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->required()
                                    ->default(now())
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->helperText('When this price becomes effective'),
                                    
                                Forms\Components\DatePicker::make('end_date')
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->helperText('Leave empty for open-ended pricing')
                                    ->afterOrEqual('start_date'),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->defaultSort('start_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Effective From')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Effective Until')
                    ->date('M d, Y')
                    ->placeholder('Ongoing')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
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
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
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
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => Auth::user()->can('update', $this->getOwnerRecord())),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (ProductPrice $record) => Auth::user()->can('update', $this->getOwnerRecord())),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ProductPrice $record) => Auth::user()->can('update', $this->getOwnerRecord())),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('update', $this->getOwnerRecord())),
                ]),
            ]);
    }
}
