<?php

namespace App\Filament\Resources\ChildResource\RelationManagers;

use App\Enums\ChildEnrolmentilledEvery;
use App\Enums\ChildEnrolmenttatus;
use App\Enums\ChildEnrolmentype;
use App\Models\Centre;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EnrolmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrolments';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Enrolment';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('EnrolmentDetails')
                    ->schema([
                        Forms\Components\Select::make('centre_id')
                            ->label('Centre')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('product_id', null); // Reset product when centre changes
                            })
                            ->options(function () {
                                // Get centres associated with this child
                                $child = $this->getOwnerRecord();

                                return $child->centres()->pluck('centres.name', 'centres.id')->toArray();
                            })
                            ->placeholder('Select a centre')
                            ->helperText('Only centres associated with this child are shown')
                            ->columnSpan(1),

                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function (callable $get) {
                                $centreId = $get('centre_id');
                                if (! $centreId) {
                                    return [];
                                }

                                // Get products associated with the selected centre
                                // Also include products that have no centres (available for all centres)
                                return Product::where(function ($query) use ($centreId) {
                                    $query->whereHas('centres', function ($centreQuery) use ($centreId) {
                                        $centreQuery->where('centres.id', $centreId);
                                    })
                                        // Include products with no centre associations (available for all centres)
                                        ->orWhereDoesntHave('centres');
                                })->pluck('name', 'id')->toArray();
                            })
                            ->placeholder('Select a centre first')
                            ->disabled(fn (callable $get): bool => ! $get('centre_id'))
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->options(ChildEnrolmenttatus::options())
                            ->default(ChildEnrolmenttatus::PENDING->value)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('type')
                            ->options(ChildEnrolmentype::options())
                            ->default(ChildEnrolmentype::FULL_TIME->value)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Billing & Schedule')
                    ->schema([
                        Forms\Components\Select::make('billed_every')
                            ->label('Billing Frequency')
                            ->options(ChildEnrolmentilledEvery::options())
                            ->default(ChildEnrolmentilledEvery::MONTHLY->value)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('date_start')
                            ->label('Start Date')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        Forms\Components\DateTimePicker::make('date_end')
                            ->label('End Date')
                            ->nullable()
                            ->columnSpan(2),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'inactive' => 'gray',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ucfirst($state->value))
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                Tables\Columns\TextColumn::make('billed_every')
                    ->label('Billing')
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_start')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date_end')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Ongoing')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->isActive())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                SelectFilter::make('centre_id')
                    ->label('Centre')
                    ->relationship('centre', 'name'),

                SelectFilter::make('status')
                    ->options(ChildEnrolmenttatus::options()),

                SelectFilter::make('type')
                    ->options(ChildEnrolmentype::options()),

                Tables\Filters\Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query): Builder => $query->where('status', ChildEnrolmenttatus::ACTIVE))
                    ->toggle(),

                Tables\Filters\Filter::make('current_only')
                    ->label('Current Only')
                    ->query(fn (Builder $query): Builder => $query->current())
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Auto-set tenant_id
                        $data['tenant_id'] = Auth::user()->current_tenant_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
