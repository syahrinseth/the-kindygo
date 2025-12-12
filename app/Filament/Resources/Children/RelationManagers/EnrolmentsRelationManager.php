<?php

namespace App\Filament\Resources\Children\RelationManagers;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\Centre;
use App\Models\Product;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentType;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\FontWeight;
use Filament\Schemas\Schema;

class EnrolmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrolments';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Enrolments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enrolment Details')
                    ->schema([
                        Select::make('centre_id')
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

                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function (callable $get) {
                                $centreId = $get('centre_id');
                                if (!$centreId) {
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
                            ->disabled(fn(callable $get): bool => !$get('centre_id'))
                            ->columnSpan(1),

                        Select::make('status')
                            ->options(ChildEnrolmentStatus::options())
                            ->default(ChildEnrolmentStatus::PENDING->value)
                            ->required()
                            ->columnSpan(1),

                        Select::make('type')
                            ->options(ChildEnrolmentType::options())
                            ->default(ChildEnrolmentType::FULL_TIME->value)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Billing & Schedule')
                    ->schema([
                        Select::make('billed_every')
                            ->label('Billing Frequency')
                            ->options(ChildEnrolmentBilledEvery::options())
                            ->default(ChildEnrolmentBilledEvery::MONTHLY->value)
                            ->required()
                            ->columnSpan(1),

                        DateTimePicker::make('date_start')
                            ->label('Start Date')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        DateTimePicker::make('date_end')
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
                TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'inactive' => 'gray',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state): string => ucfirst($state->value))
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                TextColumn::make('billed_every')
                    ->label('Billing')
                    ->formatStateUsing(fn($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                TextColumn::make('date_start')
                    ->label('Start Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('date_end')
                    ->label('End Date')
                    ->date()
                    ->placeholder('Ongoing')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(fn($record): bool => $record->isActive())
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
                    ->options(ChildEnrolmentStatus::options()),

                SelectFilter::make('type')
                    ->options(ChildEnrolmentType::options()),

                Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn(Builder $query): Builder => $query->where('status', ChildEnrolmentStatus::ACTIVE))
                    ->toggle(),

                Filter::make('current_only')
                    ->label('Current Only')
                    ->query(fn(Builder $query): Builder => $query->current())
                    ->toggle(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        // Auto-set tenant_id
                        $data['tenant_id'] = Auth::user()->current_tenant_id;
                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
