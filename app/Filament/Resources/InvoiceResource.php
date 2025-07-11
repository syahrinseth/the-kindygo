<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\Actions\MarkAsPaidAction;
use App\Filament\Resources\InvoiceResource\Actions\MakePaymentAction;
use App\Filament\Resources\InvoiceResource\Actions\ExportInvoicesAction;
use App\Filament\Resources\InvoiceResource\Actions\DownloadInvoicePdfAction;
use App\Filament\Resources\InvoiceResource\Actions\SendNotificationAction;
use App\Filament\Resources\InvoiceResource\Actions\SendBulkNotificationAction;
use App\Filament\Resources\InvoiceResource\Actions\SubmitToEInvoiceAction;
use App\Filament\Resources\InvoiceResource\Actions\SubmitBulkToEInvoiceAction;
use App\Models\Invoice;
use App\Models\Centre;
use App\Models\User;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
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
        
        // Apply the forCurrentUser scope for multi-tenant filtering
        return $query->forCurrentUser()->with([
            'user', 
            'centre',
            'payments' => function ($query) {
                $query->where('status', PaymentStatus::PAID);
            },
            'user.children' => function ($query) {
                $query->with('centres');
            }
        ]);
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
                        // Show generated invoice number on edit forms
                        TextInput::make('number')
                            ->label('Invoice Number')
                            ->disabled()
                            ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                            ->columnSpanFull()
                            ->helperText('Auto-generated in format: #{CENTRE_CODE}/{YEAR}/{NUMBER}'),
                            
                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                                    ->required()
                                    ->native(false)
                                    ->columnSpan(1),
                            ])->columns(3),
                        
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('date')
                                    ->label('Billing Month')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now()),
                                    
                                DateTimePicker::make('due_at')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now()->addDays(7)),
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
                                    ->label('Parent')
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
                    
                Section::make('E-Invoice Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('einvoice_uuid')
                                    ->label('E-Invoice UUID')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                    
                                TextInput::make('einvoice_status')
                                    ->label('E-Invoice Status')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                            ]),
                            
                        Grid::make(2)
                            ->schema([
                                TextInput::make('einvoice_submission_id')
                                    ->label('Submission ID')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                    
                                DateTimePicker::make('einvoice_submitted_at')
                                    ->label('Submitted At')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                    ->displayFormat('M d, Y H:i'),
                            ]),
                            
                        TextInput::make('einvoice_validation_url')
                            ->label('Validation URL')
                            ->disabled()
                            ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord)
                            ->url()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->einvoice_uuid)
                    ->collapsible()
                    ->collapsed(),
                    
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
                    ->sortable()
                    ->description(fn (Invoice $record): string => $record->centre->name ?? 'No centre assigned'),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Parent')
                    ->searchable()
                    ->sortable()
                    ->description(function (Invoice $record): ?string {
                        // Filter pre-loaded children by the invoice's centre
                        $children = $record->user->children->filter(function ($child) use ($record) {
                            return $child->centres->contains('id', $record->centre_id);
                        });
                        
                        if ($children->isEmpty()) {
                            return null;
                        }
                        return $children->pluck('full_name')->join(', ');
                    }),
                    
                Tables\Columns\TextColumn::make('date')
                    ->label('Billing Month')
                    ->date('M, Y')
                    ->sortable()
                    ->description(fn (Invoice $record): string => 'Due: ' . $record->due_at->format('M d, Y')),
                    
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
                    
                Tables\Columns\TextColumn::make('einvoice_status')
                    ->label('E-Invoice')
                    ->badge()
                    ->color(function (?string $state): string {
                        return match ($state) {
                            'submitted' => 'success',
                            'processing' => 'warning',
                            'valid' => 'success',
                            'invalid' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function (?string $state): string {
                        return match ($state) {
                            'submitted' => 'Submitted',
                            'processing' => 'Processing',
                            'valid' => 'Valid',
                            'invalid' => 'Invalid',
                            default => 'Not Submitted',
                        };
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('MYR', 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_discounts')
                    ->label('Discounts')
                    ->money('MYR', 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total')
                    ->money('MYR', 100)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('balance')
                    ->money('MYR', 100)
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->date()
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),
                    
                SelectFilter::make('einvoice_status')
                    ->label('E-Invoice Status')
                    ->options([
                        'submitted' => 'Submitted',
                        'processing' => 'Processing',
                        'valid' => 'Valid',
                        'invalid' => 'Invalid',
                        'not_submitted' => 'Not Submitted',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['value']) {
                            return $query;
                        }
                        
                        if ($data['value'] === 'not_submitted') {
                            return $query->whereNull('einvoice_status');
                        }
                        
                        return $query->where('einvoice_status', $data['value']);
                    }),
                    
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),
                    
                    DownloadInvoicePdfAction::make(),
                    
                    SubmitToEInvoiceAction::make(),
                    
                    SendNotificationAction::make(),
                    
                    MakePaymentAction::make(),
                    
                    MarkAsPaidAction::make(),
                    
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('update', $record)),
                    
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('delete', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    SubmitBulkToEInvoiceAction::make(),
                    SendBulkNotificationAction::make(),
                    ExportInvoicesAction::make(),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Invoice::class)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\InvoiceResource\RelationManagers\InvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
