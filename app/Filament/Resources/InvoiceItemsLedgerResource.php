<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceItemsLedgerResource\Pages;
use App\Filament\Resources\InvoiceItemsLedgerResource\RelationManagers;
use App\Models\InvoiceItem;
use App\Models\Scopes\TenantScope;
use App\Models\Scopes\BelongsToManyTenantScope;
use App\Policies\InvoiceItemsLedgerPolicy;
use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class InvoiceItemsLedgerResource extends Resource
{
    protected static ?string $model = InvoiceItem::class;
    
    protected static ?string $policy = InvoiceItemsLedgerPolicy::class;
    
    // Disable tenant ownership relationship for this resource
    protected static ?string $tenantOwnershipRelationshipName = null;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Invoice Items Ledger';
    
    protected static ?string $modelLabel = 'Invoice Item';
    
    protected static ?string $pluralModelLabel = 'Invoice Items Ledger';
    
    protected static ?string $navigationGroup = 'Financial Management';
    
    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = false;

    protected static ?string $tenantRelationshipName = null;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Item Name')
                    ->disabled(),
                    
                Forms\Components\Select::make('type')
                    ->options(InvoiceItemType::options())
                    ->disabled(),
                    
                Forms\Components\TextInput::make('price')
                    ->label('Unit Price')
                    ->disabled(),
                    
                Forms\Components\TextInput::make('quantity')
                    ->disabled(),
                    
                Forms\Components\TextInput::make('total')
                    ->label('Total Amount')
                    ->disabled(),
                    
                Forms\Components\TextInput::make('paid_amount')
                    ->label('Paid Amount')
                    ->disabled(),
                    
                Forms\Components\TextInput::make('balance_amount')
                    ->label('Balance Amount')
                    ->disabled(),
                    
                Forms\Components\Toggle::make('paid')
                    ->label('Fully Paid')
                    ->disabled(),
                    
                Forms\Components\DatePicker::make('effective_date')
                    ->label('Effective Date')
                    ->disabled(),
            ]);
    }

    // Policy handles access control - canViewAny, view, etc.
    // Custom methods for additional functionality
    
    public static function canViewAny(): bool
    {
        return Auth::user()?->can('viewAny', InvoiceItemsLedgerResource::class) ?? false;
    }

    public static function canView($record): bool
    {
        return Auth::user()?->can('view', [InvoiceItemsLedgerResource::class, $record]) ?? false;
    }
    
    public static function canExport(): bool
    {
        return Auth::user()?->can('export', InvoiceItemsLedgerResource::class);
    }

    public static function canViewFinancials(): bool
    {
        return Auth::user()?->can('viewFinancials', InvoiceItemsLedgerResource::class);
    }

    public static function getEloquentQuery(): Builder
    {
        // Start with the base query and disable tenant scoping for relationships
        $query = parent::getEloquentQuery()
            ->with([
                'invoice',
                'child',
                'product',
            ]);
        
        $user = Auth::user();

        // Filter data based on user role
        if ($user && $user->hasRole(['Principal', 'Teacher'])) {
            // Get user's assigned centre IDs
            $userCentreIds = $user->centres->pluck('id')->toArray();
            
            // Filter invoice items to only show those from assigned centres
            $query->whereHas('invoice', function (Builder $query) use ($userCentreIds) {
                $query->whereIn('centre_id', $userCentreIds);
            });
        }
        
        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex()
                    ->width(50),

                TextColumn::make('effective_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->description(fn ($record) => $record->effective_date?->format('l')),

                TextColumn::make('invoice.number')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => "Invoice #{$record->invoice?->number}"),
                    
                TextColumn::make('name')
                    ->label('Description')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->invoice->centre->name ?? ''),

                TextColumn::make('type')
                    ->label('Transaction Type')
                    ->badge()
                    ->colors([
                        'primary' => InvoiceItemType::PRODUCT->value,
                        'warning' => InvoiceItemType::INVOICE_DISCOUNT->value,
                    ])
                    ->formatStateUsing(fn ($state) => $state?->label() ?? $state),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('MYR', 100)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('discount')
                    ->label('Unit Discount')
                    ->money('MYR', 100)
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Debit Amount')
                    ->money('MYR', 100)
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->color('danger'),
                    
                TextColumn::make('paid_amount')
                    ->label('Credit Amount')
                    ->money('MYR', 100)
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                    
                TextColumn::make('balance_amount')
                    ->label('Outstanding Balance')
                    ->money('MYR', 100)
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->description('Remaining amount due'),

                TextColumn::make('paid')
                    ->label('Status')
                    ->badge()
                    ->alignCenter()
                    ->color(fn ($state, $record) => match (true) {
                        $state => 'success',
                        $record->paid_amount > 0 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(function ($state, $record) {
                        if ($state) return 'PAID';
                        if ($record->paid_amount > 0) return 'PARTIAL';
                        return 'UNPAID';
                    }),

                TextColumn::make('invoice.status')
                    ->label('Invoice Status')
                    ->badge()
                    ->alignCenter()
                    ->colors([
                        'success' => InvoiceStatus::PAID,
                        'warning' => InvoiceStatus::PENDING,
                        'danger' => InvoiceStatus::OVERDUE,
                        'gray' => InvoiceStatus::DRAFT,
                    ])
                    ->formatStateUsing(fn ($state) => strtoupper($state?->label() ?? $state)),
            ])
            ->filters([
                SelectFilter::make('centre')
                    ->label('Centre')
                    ->relationship('invoice.centre', 'name')
                    ->searchable()
                    ->preload(),
                    
                SelectFilter::make('type')
                    ->options(InvoiceItemType::options()),
                    
                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        PaymentStatus::PAID->value => PaymentStatus::PAID->label(),
                        PaymentStatus::UNPAID->value => PaymentStatus::UNPAID->label(),
                        PaymentStatus::PARTIALLY_PAID->value => PaymentStatus::PARTIALLY_PAID->label(),
                        PaymentStatus::PENDING->value => PaymentStatus::PENDING->label(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === PaymentStatus::PAID->value,
                            fn (Builder $query) => $query->where('paid', true),
                        )->when(
                            $data['value'] === PaymentStatus::UNPAID->value,
                            fn (Builder $query) => $query->where('paid', false)->where('paid_amount', 0),
                        )->when(
                            $data['value'] === PaymentStatus::PARTIALLY_PAID->value,
                            fn (Builder $query) => $query->where('paid', false)->where('paid_amount', '>', 0),
                        )->when(
                            $data['value'] === PaymentStatus::PENDING->value,
                            fn (Builder $query) => $query->where('paid', false),
                        );
                    }),
                    
                // SelectFilter::make('invoice.status')
                //     ->label('Invoice Status')
                //     ->options(collect(InvoiceStatus::cases())->mapWithKeys(fn($case) => [
                //         $case->value => $case->label()
                //     ])->toArray()),
                    
                Filter::make('effective_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('effective_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('effective_date', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Remove bulk actions for ledger view
            ])
            ->defaultSort('effective_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceItemsLedgers::route('/'),
            'view' => Pages\ViewInvoiceItemsLedger::route('/{record}'),
        ];
    }
}
