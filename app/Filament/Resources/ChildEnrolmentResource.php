<?php

namespace App\Filament\Resources;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Services\ChildEnrolmentInvoiceService;
use Filament\Actions\BulkActionGroup;
use App\Filament\Resources\ChildEnrolmentResource\RelationManagers\InvoiceItemsRelationManager;
use App\Filament\Resources\ChildEnrolmentResource\Pages\ListChildEnrolments;
use App\Filament\Resources\ChildEnrolmentResource\Pages\CreateChildEnrolment;
use App\Filament\Resources\ChildEnrolmentResource\Pages\ViewChildEnrolment;
use App\Filament\Resources\ChildEnrolmentResource\Pages\EditChildEnrolment;
use App\Filament\Resources\ChildEnrolmentResource\Pages;
use App\Filament\Resources\ChildEnrolmentResource\RelationManagers;
use App\Models\ChildEnrolment;
use App\Models\Child;
use App\Models\Product;
use App\Models\Centre;
use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentBilledEvery;
use App\Enums\ChildEnrolmentType;
use App\Policies\ChildEnrolmentPolicy;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class ChildEnrolmentResource extends Resource
{
    protected static ?string $model = ChildEnrolment::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Child Enrolments';

    protected static ?string $modelLabel = 'Child Enrolment';

    protected static ?string $pluralModelLabel = 'Child Enrolments';

    protected static string | \UnitEnum | null $navigationGroup = 'Child Management';

    protected static ?int $navigationSort = 2;

    protected static string $policy = ChildEnrolmentPolicy::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enrolment Details')
                    ->schema([
                        Select::make('child_id')
                            ->relationship('child', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('centre_id', null); // Reset centre when child changes
                            })
                            ->disabled(fn(string $operation): bool => $operation === 'edit')
                            ->helperText(
                                fn(string $operation): ?string =>
                                $operation === 'edit'
                                    ? 'Child cannot be changed after enrolment is created. Create a new enrolment if needed.'
                                    : null
                            )
                            ->columnSpan(1),

                        Select::make('centre_id')
                            ->label('Centre')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('product_id', null); // Reset product when centre changes
                            })
                            ->options(function (callable $get) {
                                $childId = $get('child_id');
                                if (!$childId) {
                                    return [];
                                }

                                // Get centres associated with the selected child
                                $child = Child::find($childId);
                                if (!$child) {
                                    return [];
                                }

                                $centres = $child->centres()->pluck('centres.name', 'centres.id')->toArray();

                                // If no centres associated, return empty array (will show placeholder)
                                return $centres;
                            })
                            ->placeholder(function (callable $get) {
                                $childId = $get('child_id');
                                if (!$childId) {
                                    return 'Select a child first';
                                }

                                $child = Child::find($childId);
                                if ($child && $child->centres()->count() === 0) {
                                    return 'No centres associated with this child';
                                }

                                return 'Select a centre';
                            })
                            ->helperText(function (callable $get, string $operation) {
                                if ($operation === 'edit') {
                                    return 'Centre cannot be changed after enrolment is created. Create a new enrolment if needed.';
                                }

                                $childId = $get('child_id');
                                if (!$childId) {
                                    return 'Only centres associated with the selected child will be shown';
                                }

                                $child = Child::find($childId);
                                if ($child && $child->centres()->count() === 0) {
                                    return 'This child is not associated with any centres. Please associate the child with a centre first.';
                                }

                                return 'Only centres associated with the selected child are shown';
                            })
                            ->disabled(function (callable $get, string $operation): bool {
                                if ($operation === 'edit') {
                                    return true;
                                }

                                if (!$get('child_id')) {
                                    return true;
                                }

                                $child = Child::find($get('child_id'));
                                return $child && $child->centres()->count() === 0;
                            })
                            ->rules([
                                function (callable $get) {
                                    return function (string $attribute, $value, Closure $fail) use ($get) {
                                        $childId = $get('child_id');
                                        if ($childId && $value) {
                                            $child = Child::find($childId);
                                            if ($child && !$child->centres()->where('centres.id', $value)->exists()) {
                                                $fail('The selected centre is not associated with the selected child.');
                                            }
                                        }
                                    };
                                },
                            ])
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
                                })->active()->pluck('name', 'id')->toArray();
                            })
                            ->placeholder(function (callable $get) {
                                $centreId = $get('centre_id');
                                if (!$centreId) {
                                    return 'Select a centre first';
                                }
                                return 'Select a product';
                            })
                            ->helperText(function (callable $get, string $operation) {
                                if ($operation === 'edit') {
                                    return 'Product cannot be changed after enrolment is created. Create a new enrolment if needed.';
                                }

                                $centreId = $get('centre_id');
                                if (!$centreId) {
                                    return 'Products will be filtered based on the selected centre';
                                }
                                return 'Showing products available for the selected centre and products available for all centres';
                            })
                            ->disabled(function (callable $get, string $operation): bool {
                                return $operation === 'edit' || !$get('centre_id');
                            })
                            ->rules([
                                function (callable $get) {
                                    return function (string $attribute, $value, Closure $fail) use ($get) {
                                        $centreId = $get('centre_id');
                                        if ($centreId && $value) {
                                            $product = Product::find($value);
                                            if ($product) {
                                                // Check if product is associated with the centre OR has no centre associations
                                                $hasAccess = $product->centres()->where('centres.id', $centreId)->exists()
                                                    || $product->centres()->count() === 0;

                                                if (!$hasAccess) {
                                                    $fail('The selected product is not available for the selected centre.');
                                                }
                                            }
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
                    ])
                    ->columns(2),

                Section::make('Program Details')
                    ->schema([
                        Select::make('type')
                            ->options(ChildEnrolmentType::options())
                            ->default(ChildEnrolmentType::FULL_TIME->value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state) {
                                // Reset billed_every when type changes
                                $oneTimeOnlyTypes = [
                                    ChildEnrolmentType::TRIAL->value,
                                    // ChildEnrolmentType::SUMMER_PROGRAM->value,
                                    // ChildEnrolmentType::AFTER_SCHOOL->value,
                                    // ChildEnrolmentType::HOLIDAY_PROGRAM->value,
                                ];

                                if (in_array($state, $oneTimeOnlyTypes)) {
                                    $set('billed_every', ChildEnrolmentBilledEvery::ONE_TIME->value);
                                } else {
                                    $set('billed_every', ChildEnrolmentBilledEvery::MONTHLY->value);
                                }
                            })
                            ->columnSpan(1),

                        Select::make('billed_every')
                            ->options(function (callable $get) {
                                $type = $get('type');

                                // Types that should only show ONE_TIME option
                                $oneTimeOnlyTypes = [
                                    ChildEnrolmentType::TRIAL->value,
                                    // ChildEnrolmentType::SUMMER_PROGRAM->value,
                                    // ChildEnrolmentType::AFTER_SCHOOL->value,
                                    // ChildEnrolmentType::HOLIDAY_PROGRAM->value,
                                ];

                                if (in_array($type, $oneTimeOnlyTypes)) {
                                    return [
                                        ChildEnrolmentBilledEvery::ONE_TIME->value => 'One Time'
                                    ];
                                }

                                return ChildEnrolmentBilledEvery::options();
                            })
                            ->default(function (callable $get) {
                                $type = $get('type');

                                // Types that should default to ONE_TIME
                                $oneTimeOnlyTypes = [
                                    ChildEnrolmentType::TRIAL->value,
                                    ChildEnrolmentType::SUMMER_PROGRAM->value,
                                    ChildEnrolmentType::AFTER_SCHOOL->value,
                                    ChildEnrolmentType::HOLIDAY_PROGRAM->value,
                                ];

                                if (in_array($type, $oneTimeOnlyTypes)) {
                                    return ChildEnrolmentBilledEvery::ONE_TIME->value;
                                }

                                return ChildEnrolmentBilledEvery::MONTHLY->value;
                            })
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Duration')
                    ->schema([
                        DateTimePicker::make('date_start')
                            ->label('Start Date')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),

                        DateTimePicker::make('date_end')
                            ->label('End Date')
                            ->nullable()
                            ->columnSpan(1),
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
                                    ->options(function (callable $get) {
                                        $centreId = $get('../../centre_id'); // Get centre_id from parent form
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
                                        })->active()->pluck('name', 'id')->toArray();
                                    })
                                    ->placeholder(function (callable $get) {
                                        $centreId = $get('../../centre_id');
                                        if (!$centreId) {
                                            return 'Select a centre first';
                                        }
                                        return 'Select a product';
                                    })
                                    ->disabled(function (callable $get): bool {
                                        return !$get('../../centre_id');
                                    })
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
                                if (!isset($state['product_id'])) {
                                    return 'New Additional Product';
                                }

                                $product = Product::find($state['product_id']);
                                if (!$product) {
                                    return 'Additional Product';
                                }

                                $billingFreq = isset($state['billed_every'])
                                    ? ucwords(str_replace('_', ' ', $state['billed_every']))
                                    : 'Monthly';

                                return "{$product->name} - {$billingFreq}";
                            })
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->description('Add additional products to this enrolment with their own billing frequencies'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('child.full_name')
                    ->label('Child')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight(FontWeight::Medium),

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

                TextColumn::make('additional_products_count')
                    ->label('Additional Products')
                    ->getStateUsing(function (ChildEnrolment $record): string {
                        $additionalProducts = $record->additional_products ?? [];
                        $count = count($additionalProducts);

                        if ($count === 0) {
                            return 'None';
                        }

                        return $count . ' product' . ($count > 1 ? 's' : '');
                    })
                    ->badge()
                    ->color(function (ChildEnrolment $record): string {
                        $count = count($record->additional_products ?? []);
                        return $count > 0 ? 'info' : 'gray';
                    })
                    ->tooltip(function (ChildEnrolment $record): ?string {
                        $additionalProducts = $record->additional_products ?? [];

                        if (empty($additionalProducts)) {
                            return null;
                        }

                        $productNames = [];
                        foreach ($additionalProducts as $item) {
                            if (isset($item['product_id'])) {
                                $product = Product::find($item['product_id']);
                                if ($product) {
                                    $billingFreq = isset($item['billed_every'])
                                        ? ucwords(str_replace('_', ' ', $item['billed_every']))
                                        : 'Monthly';
                                    $productNames[] = "{$product->name} ({$billingFreq})";
                                }
                            }
                        }

                        return implode(', ', $productNames);
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'draft' => 'gray',
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
                    ->label('Billing Frequency')
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
                    ->getStateUsing(fn(ChildEnrolment $record): bool => $record->isActive())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

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
                SelectFilter::make('centre_id')
                    ->label('Centre')
                    ->relationship('centre', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options(ChildEnrolmentStatus::options())
                    ->multiple(),

                SelectFilter::make('type')
                    ->options(ChildEnrolmentType::options())
                    ->multiple(),

                SelectFilter::make('billed_every')
                    ->label('Billing Frequency')
                    ->options(ChildEnrolmentBilledEvery::options())
                    ->multiple(),

                Filter::make('active_only')
                    ->label('Active Enrolments')
                    ->query(fn(Builder $query): Builder => $query->where('status', ChildEnrolmentStatus::ACTIVE))
                    ->toggle(),

                Filter::make('current_only')
                    ->label('Current Enrolments')
                    ->query(fn(Builder $query): Builder => $query->current())
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn(ChildEnrolment $record): bool => Auth::user()->can('view', $record)),
                    EditAction::make()
                        ->visible(fn(ChildEnrolment $record): bool => Auth::user()->can('update', $record)),
                    Action::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->action(function (ChildEnrolment $record) {
                            $invoiceService = app(ChildEnrolmentInvoiceService::class);
                            $enrolments = $invoiceService->getRelatedEnrolments($record);
                            if (empty($enrolments)) {
                                Notification::make()
                                    ->title('No Invoices Needed')
                                    ->body('All enrolments for this parent at this centre already have current invoices.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            $invoices = $invoiceService->generateInvoicesForEnrolments($enrolments);

                            $childNames = $enrolments->map(fn($e) => $e->child->full_name)->unique()->implode(', ');

                            Notification::make()
                                ->title('Invoices Generated')
                                ->body("Successfully generated {$invoices->count()} invoice(s) for: {$childNames}.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->visible(fn(ChildEnrolment $record): bool => Auth::user()->can('update', $record)),
                    // Actions\DeleteAction::make()
                    //     ->visible(fn (ChildEnrolment $record): bool => Auth::user()->can('delete', $record)), // temp disabled
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Actions\DeleteBulkAction::make()
                    //     ->visible(fn (): bool => Auth::user()->can('delete', [Auth::user(), ChildEnrolment::class])), // temp disabled
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            InvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChildEnrolments::route('/'),
            'create' => CreateChildEnrolment::route('/create'),
            'view' => ViewChildEnrolment::route('/{record}'),
            'edit' => EditChildEnrolment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->active()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if (!$user) {
            return $query->whereRaw('1 = 0'); // Return empty result if no user
        }

        // Super Admin can see all enrolments
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        // Admin can see all enrolments in their tenant
        if ($user->hasRole('Admin')) {
            return $query->where('tenant_id', $user->current_tenant_id);
        }

        // Principal and Teacher can see enrolments for centres they have access to
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            $userCentreIds = $user->centres()
                ->where('centres.tenant_id', $user->current_tenant_id)
                ->pluck('centres.id');

            return $query->where('tenant_id', $user->current_tenant_id)
                ->whereIn('centre_id', $userCentreIds);
        }

        // Parents can see enrolments for their children only
        if ($user->hasRole('Parent')) {
            $childIds = $user->children()->pluck('children.id');

            return $query->where('tenant_id', $user->current_tenant_id)
                ->whereIn('child_id', $childIds);
        }

        // Default: no access
        return $query->whereRaw('1 = 0');
    }
}
