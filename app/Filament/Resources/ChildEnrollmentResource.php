<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChildEnrollmentResource\Pages;
use App\Filament\Resources\ChildEnrollmentResource\RelationManagers;
use App\Models\ChildEnrollment;
use App\Models\Child;
use App\Models\Product;
use App\Models\Centre;
use App\Enums\ChildEnrollmentStatus;
use App\Enums\ChildEnrollmentBilledEvery;
use App\Enums\ChildEnrollmentType;
use App\Policies\ChildEnrollmentPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\FontWeight;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ChildEnrollmentResource extends Resource
{
    protected static ?string $model = ChildEnrollment::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    
    protected static ?string $navigationLabel = 'Child Enrollments';
    
    protected static ?string $modelLabel = 'Child Enrollment';
    
    protected static ?string $pluralModelLabel = 'Child Enrollments';
    
    protected static ?string $navigationGroup = 'Child Management';
    
    protected static ?int $navigationSort = 2;
    
    protected static string $policy = ChildEnrollmentPolicy::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Enrollment Details')
                    ->schema([
                        Forms\Components\Select::make('child_id')
                            ->relationship('child', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('centre_id', null); // Reset centre when child changes
                            })
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->helperText(fn (string $operation): ?string => 
                                $operation === 'edit' 
                                    ? 'Child cannot be changed after enrollment is created. Create a new enrollment if needed.' 
                                    : null
                            )
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('centre_id')
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
                                    return 'Centre cannot be changed after enrollment is created. Create a new enrollment if needed.';
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
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
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
                            
                        Forms\Components\Select::make('product_id')
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
                                    return 'Product cannot be changed after enrollment is created. Create a new enrollment if needed.';
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
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
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
                            
                        Forms\Components\Select::make('status')
                            ->options(ChildEnrollmentStatus::options())
                            ->default(ChildEnrollmentStatus::PENDING->value)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Program Details')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->options(ChildEnrollmentType::options())
                            ->default(ChildEnrollmentType::FULL_TIME->value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state) {
                                // Reset billed_every when type changes
                                $oneTimeOnlyTypes = [
                                    ChildEnrollmentType::TRIAL->value,
                                    // ChildEnrollmentType::SUMMER_PROGRAM->value,
                                    // ChildEnrollmentType::AFTER_SCHOOL->value,
                                    // ChildEnrollmentType::HOLIDAY_PROGRAM->value,
                                ];
                                
                                if (in_array($state, $oneTimeOnlyTypes)) {
                                    $set('billed_every', ChildEnrollmentBilledEvery::ONE_TIME->value);
                                } else {
                                    $set('billed_every', ChildEnrollmentBilledEvery::MONTHLY->value);
                                }
                            })
                            ->columnSpan(1),
                            
                        Forms\Components\Select::make('billed_every')
                            ->options(function (callable $get) {
                                $type = $get('type');
                                
                                // Types that should only show ONE_TIME option
                                $oneTimeOnlyTypes = [
                                    ChildEnrollmentType::TRIAL->value,
                                    // ChildEnrollmentType::SUMMER_PROGRAM->value,
                                    // ChildEnrollmentType::AFTER_SCHOOL->value,
                                    // ChildEnrollmentType::HOLIDAY_PROGRAM->value,
                                ];
                                
                                if (in_array($type, $oneTimeOnlyTypes)) {
                                    return [
                                        ChildEnrollmentBilledEvery::ONE_TIME->value => 'One Time'
                                    ];
                                }
                                
                                return ChildEnrollmentBilledEvery::options();
                            })
                            ->default(function (callable $get) {
                                $type = $get('type');
                                
                                // Types that should default to ONE_TIME
                                $oneTimeOnlyTypes = [
                                    ChildEnrollmentType::TRIAL->value,
                                    ChildEnrollmentType::SUMMER_PROGRAM->value,
                                    ChildEnrollmentType::AFTER_SCHOOL->value,
                                    ChildEnrollmentType::HOLIDAY_PROGRAM->value,
                                ];
                                
                                if (in_array($type, $oneTimeOnlyTypes)) {
                                    return ChildEnrollmentBilledEvery::ONE_TIME->value;
                                }
                                
                                return ChildEnrollmentBilledEvery::MONTHLY->value;
                            })
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Duration')
                    ->schema([
                        Forms\Components\DateTimePicker::make('date_start')
                            ->label('Start Date')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),
                            
                        Forms\Components\DateTimePicker::make('date_end')
                            ->label('End Date')
                            ->nullable()
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Additional Products')
                    ->schema([
                        Forms\Components\Repeater::make('additional_products')
                            ->schema([
                                Forms\Components\Select::make('product_id')
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
                                    
                                Forms\Components\Select::make('billed_every')
                                    ->label('Billing Frequency')
                                    ->options(ChildEnrollmentBilledEvery::options())
                                    ->default(ChildEnrollmentBilledEvery::MONTHLY->value)
                                    ->required()
                                    ->columnSpan(1),
                                    
                                Forms\Components\DateTimePicker::make('date_start')
                                    ->label('Start Date')
                                    ->default(now())
                                    ->columnSpan(1),
                                    
                                Forms\Components\DateTimePicker::make('date_end')
                                    ->label('End Date')
                                    ->nullable()
                                    ->columnSpan(1),
                                    
                                Forms\Components\Textarea::make('notes')
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
                    ->description('Add additional products to this enrollment with their own billing frequencies'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('child.full_name')
                    ->label('Child')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight(FontWeight::Medium),
                    
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
                    
                Tables\Columns\TextColumn::make('additional_products_count')
                    ->label('Additional Products')
                    ->getStateUsing(function (ChildEnrollment $record): string {
                        $additionalProducts = $record->additional_products ?? [];
                        $count = count($additionalProducts);
                        
                        if ($count === 0) {
                            return 'None';
                        }
                        
                        return $count . ' product' . ($count > 1 ? 's' : '');
                    })
                    ->badge()
                    ->color(function (ChildEnrollment $record): string {
                        $count = count($record->additional_products ?? []);
                        return $count > 0 ? 'info' : 'gray';
                    })
                    ->tooltip(function (ChildEnrollment $record): ?string {
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
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'draft' => 'gray',
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
                    ->label('Billing Frequency')
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
                    ->getStateUsing(fn (ChildEnrollment $record): bool => $record->isActive())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
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
                SelectFilter::make('centre_id')
                    ->label('Centre')
                    ->relationship('centre', 'name')
                    ->searchable()
                    ->preload(),
                    
                SelectFilter::make('status')
                    ->options(ChildEnrollmentStatus::options())
                    ->multiple(),
                    
                SelectFilter::make('type')
                    ->options(ChildEnrollmentType::options())
                    ->multiple(),
                    
                SelectFilter::make('billed_every')
                    ->label('Billing Frequency')
                    ->options(ChildEnrollmentBilledEvery::options())
                    ->multiple(),
                    
                Tables\Filters\Filter::make('active_only')
                    ->label('Active Enrollments')
                    ->query(fn (Builder $query): Builder => $query->where('status', ChildEnrollmentStatus::ACTIVE))
                    ->toggle(),
                    
                Tables\Filters\Filter::make('current_only')
                    ->label('Current Enrollments')
                    ->query(fn (Builder $query): Builder => $query->current())
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->visible(fn (ChildEnrollment $record): bool => Auth::user()->can('view', $record)),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (ChildEnrollment $record): bool => Auth::user()->can('update', $record)),
                    Tables\Actions\Action::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-plus')
                        ->color('success')
                        ->action(function (ChildEnrollment $record) {
                            $invoiceService = app(\App\Services\ChildEnrollmentInvoiceService::class);
                            $enrollments = $invoiceService->getRelatedEnrollments($record);
                            if (empty($enrollments)) {
                                Notification::make()
                                    ->title('No Invoices Needed')
                                    ->body('All enrollments for this parent at this centre already have current invoices.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                            $invoices = $invoiceService->generateInvoicesForEnrollments($enrollments);

                            $childNames = $enrollments->map(fn($e) => $e->child->full_name)->unique()->implode(', ');

                            Notification::make()
                                ->title('Invoices Generated')
                                ->body("Successfully generated {$invoices->count()} invoice(s) for: {$childNames}.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->visible(fn (ChildEnrollment $record): bool => Auth::user()->can('update', $record)),
                    // Tables\Actions\DeleteAction::make()
                    //     ->visible(fn (ChildEnrollment $record): bool => Auth::user()->can('delete', $record)), // temp disabled
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make()
                    //     ->visible(fn (): bool => Auth::user()->can('delete', [Auth::user(), ChildEnrollment::class])), // temp disabled
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
            'index' => Pages\ListChildEnrollments::route('/'),
            'create' => Pages\CreateChildEnrollment::route('/create'),
            'view' => Pages\ViewChildEnrollment::route('/{record}'),
            'edit' => Pages\EditChildEnrollment::route('/{record}/edit'),
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
        
        // Super Admin can see all enrollments
        if ($user->hasRole('Super Admin')) {
            return $query;
        }
        
        // Admin can see all enrollments in their tenant
        if ($user->hasRole('Admin')) {
            return $query->where('tenant_id', $user->current_tenant_id);
        }
        
        // Principal and Teacher can see enrollments for centres they have access to
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            $userCentreIds = $user->centres()
                ->where('centres.tenant_id', $user->current_tenant_id)
                ->pluck('centres.id');
                
            return $query->where('tenant_id', $user->current_tenant_id)
                        ->whereIn('centre_id', $userCentreIds);
        }
        
        // Parents can see enrollments for their children only
        if ($user->hasRole('Parent')) {
            $childIds = $user->children()->pluck('children.id');
            
            return $query->where('tenant_id', $user->current_tenant_id)
                        ->whereIn('child_id', $childIds);
        }
        
        // Default: no access
        return $query->whereRaw('1 = 0');
    }
}
