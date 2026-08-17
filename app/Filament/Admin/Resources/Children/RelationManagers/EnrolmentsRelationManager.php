<?php

namespace App\Filament\Admin\Resources\Children\RelationManagers;

use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Models\Centre;
use App\Models\Product;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Auth;

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
                            ->afterStateUpdated(function (callable $set): void {
                                $set('product_id', null);
                            })
                            ->options(function (): array {
                                // The relation manager is already tenant-scoped through the Centre model.
                                return Centre::query()->pluck('name', 'id')->all();
                            })
                            ->placeholder('Select a centre')
                            ->helperText('Only centres in the current tenant are shown')
                            ->columnSpan(1),

                        Select::make('product_id')
                            ->label('Product')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function (callable $get): array {
                                return $this->getProductsForCentre($get('centre_id'));
                            })
                            ->placeholder('Select a centre first')
                            ->disabled(fn (callable $get): bool => ! $get('centre_id'))
                            ->rules([
                                function (callable $get): Closure {
                                    return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                        $centreId = $get('centre_id');

                                        if (! $centreId || ! $value) {
                                            return;
                                        }

                                        $product = Product::find($value);

                                        if (! $product) {
                                            return;
                                        }

                                        $hasAccess = $product->centres()->whereKey($centreId)->exists();

                                        if (! $hasAccess) {
                                            $fail('The selected product is not available for the selected centre.');
                                        }
                                    };
                                },
                            ])
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
                            ->live()
                            ->afterStateUpdated(function (callable $set, mixed $state): void {
                                $set(
                                    'billed_every',
                                    $state === ChildEnrolmentType::TRIAL->value
                                        ? ChildEnrolmentBilledEvery::ONE_TIME->value
                                        : ChildEnrolmentBilledEvery::MONTHLY->value,
                                );
                            })
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Billing & Schedule')
                    ->schema([
                        Select::make('billed_every')
                            ->label('Billing Frequency')
                            ->options(function (callable $get): array {
                                if ($get('type') === ChildEnrolmentType::TRIAL->value) {
                                    return [
                                        ChildEnrolmentBilledEvery::ONE_TIME->value => 'One Time',
                                    ];
                                }

                                return ChildEnrolmentBilledEvery::options();
                            })
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

                Section::make('Additional Products')
                    ->schema([
                        Repeater::make('additional_products')
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->options(function (callable $get): array {
                                        return $this->getProductsForCentre($get('../../centre_id'));
                                    })
                                    ->placeholder('Select a centre first')
                                    ->disabled(fn (callable $get): bool => ! $get('../../centre_id'))
                                    ->columnSpan(2),

                                Select::make('billed_every')
                                    ->label('Billing Frequency')
                                    ->options(ChildEnrolmentBilledEvery::options())
                                    ->default(ChildEnrolmentBilledEvery::MONTHLY->value)
                                    ->required()
                                    ->columnSpan(1),

                                DateTimePicker::make('date_start')
                                    ->label('Start Date')
                                    ->default(now())
                                    ->columnSpan(1),

                                DateTimePicker::make('date_end')
                                    ->label('End Date')
                                    ->nullable()
                                    ->columnSpan(1),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->placeholder('Optional notes for this additional product')
                                    ->columnSpan(3),
                            ])
                            ->columns(3)
                            ->collapsible()
                            ->collapsed()
                            ->addActionLabel('Add Additional Product')
                            ->reorderableWithButtons()
                            ->defaultItems(0)
                            ->itemLabel(function (array $state): ?string {
                                if (! isset($state['product_id'])) {
                                    return 'New Additional Product';
                                }

                                $product = Product::find($state['product_id']);

                                if (! $product) {
                                    return 'Additional Product';
                                }

                                $billingFrequency = isset($state['billed_every'])
                                    ? ucwords(str_replace('_', ' ', $state['billed_every']))
                                    : 'Monthly';

                                return "{$product->name} - {$billingFrequency}";
                            }),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->description('Add additional products to this enrolment with their own billing frequencies'),
            ]);
    }

    /**
     * Get active products assigned to a tenant centre.
     *
     * @return array<int|string, string>
     */
    protected function getProductsForCentre(int|string|null $centreId): array
    {
        if (! $centreId || ! Centre::query()->whereKey($centreId)->exists()) {
            return [];
        }

        return Product::query()
            ->whereHas('centres', function (Builder $query) use ($centreId): void {
                $query->whereKey($centreId);
            })
            ->active()
            ->pluck('name', 'id')
            ->all();
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

                TextColumn::make('type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                TextColumn::make('billed_every')
                    ->label('Billing')
                    ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value)))
                    ->sortable(),

                TextColumn::make('next_bill_date')
                    ->label('Next Billing')
                    ->date('d M Y')
                    ->placeholder('-')
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
                    ->options(ChildEnrolmentStatus::options()),

                SelectFilter::make('type')
                    ->options(ChildEnrolmentType::options()),

                Filter::make('active_only')
                    ->label('Active Only')
                    ->query(fn (Builder $query): Builder => $query->where('status', ChildEnrolmentStatus::ACTIVE))
                    ->toggle(),

                Filter::make('current_only')
                    ->label('Current Only')
                    ->query(fn (Builder $query): Builder => $query->current())
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
