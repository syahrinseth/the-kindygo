<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\Actions\MarkAsPaidAction;
use App\Filament\Resources\InvoiceResource\Actions\ExportInvoicesAction;
use App\Models\Invoice;
use App\Models\Centre;
use App\Models\User;
use App\Enums\InvoiceStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationGroup = 'Finance';
    
    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('status', InvoiceStatus::PENDING)->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return static::getEloquentQuery()->where('status', InvoiceStatus::OVERDUE)->count() > 0
            ? 'danger'
            : 'warning';
    }

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        
        // If user is Principal, restrict to their centres
        if ($user->hasAnyRole(['Principal', 'Teacher', 'Parent'])) {
            $query->whereIn('centre_id', $user->centres()->pluck('centres.id'));
        }

        // If user is Parent, restrict to their invoices
        if ($user->hasRole('Parent')) {
            $query->where('user_id', Auth::id())
                ->where('status', '!=', InvoiceStatus::DRAFT->value);
        }

        return $query;
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', Invoice::class);
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create', Invoice::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', Invoice::class);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Invoice Details')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextInput::make('number')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('INV-XXXXXXXX')
                                    ->helperText('A unique invoice number')
                                    ->columnSpan(1),
                                
                                Select::make('status')
                                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now()),
                                    
                                DateTimePicker::make('due_at')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now()->addDays(30)),
                            ]),
                            
                        Grid::make(2)
                            ->schema([
                                Select::make('centre_id')
                                    ->label('Centre')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (!$user->current_tenant_id) {
                                            return [];
                                        }
                                        
                                        $query = Centre::where('tenant_id', $user->current_tenant_id);
                                        
                                        // If Principal, limit to their centres
                                        if ($user->hasRole('Principal')) {
                                            $query->whereHas('users', function (Builder $q) use ($user) {
                                                $q->where('users.id', $user->id);
                                            });
                                        }
                                        
                                        return $query->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                                    
                                Select::make('user_id')
                                    ->label('User')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (!$user->current_tenant_id) {
                                            return [];
                                        }
                                        
                                        return User::whereHas('tenants', function (Builder $query) use ($user) {
                                            $query->where('tenants.id', $user->current_tenant_id);
                                        })->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                            ]),
                    ]),
                    
                Section::make('Financial Details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('total_items')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0),
                                    
                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->prefix('RM')
                                    ->step(0.01)
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        // Convert from cents to decimal for display
                                        $component->state(number_format($state / 100, 2, '.', ''));
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)), // Convert to cents for storage
                                    
                                TextInput::make('total_discounts')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->prefix('RM')
                                    ->step(0.01)
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        // Convert from cents to decimal for display
                                        $component->state(number_format($state / 100, 2, '.', ''));
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)), // Convert to cents for storage
                                    
                                TextInput::make('total')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->prefix('RM')
                                    ->step(0.01)
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        // Convert from cents to decimal for display
                                        $component->state(number_format($state / 100, 2, '.', ''));
                                    })
                                    ->dehydrateStateUsing(fn ($state) => (int) ($state * 100)), // Convert to cents for storage
                            ]),
                    ]),
                    
                Forms\Components\Hidden::make('tenant_id')
                    ->default(function () {
                        return Auth::user()->current_tenant_id;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('date')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('due_at')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100) // Convert cents to decimal for display
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_discounts')
                    ->label('Discounts')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100) // Convert cents to decimal for display
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total')
                    ->money('MYR')
                    ->formatStateUsing(fn ($state) => $state / 100) // Convert cents to decimal for display
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),
                    
                Filter::make('overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                
                SelectFilter::make('centre')
                    ->relationship('centre', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),
                
                MarkAsPaidAction::make(),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn (Invoice $record) => Auth::user()->can('update', $record)),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Invoice $record) => Auth::user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ExportInvoicesAction::make(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Invoice::class)),
                ]),
            ]);
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
