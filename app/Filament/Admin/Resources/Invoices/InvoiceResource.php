<?php

namespace App\Filament\Admin\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Invoices\Actions\BulkPayInvoicesBulkAction;
use App\Filament\Admin\Resources\Invoices\Actions\DownloadInvoicePdfAction;
use App\Filament\Admin\Resources\Invoices\Actions\ExportInvoicesAction;
use App\Filament\Admin\Resources\Invoices\Actions\MakePaymentAction;
use App\Filament\Admin\Resources\Invoices\Actions\MarkAsPaidAction;
use App\Filament\Admin\Resources\Invoices\Actions\SendBulkNotificationAction;
use App\Filament\Admin\Resources\Invoices\Actions\SendNotificationAction;
use App\Filament\Admin\Resources\Invoices\Actions\SubmitBulkToEInvoiceAction;
use App\Filament\Admin\Resources\Invoices\Actions\SubmitToEInvoiceAction;
use App\Filament\Admin\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\Invoices\RelationManagers\InvoiceItemsRelationManager;
use App\Models\Centre;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

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
            },
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->schema([
                        // Show generated invoice number on edit forms
                        TextInput::make('number')
                            ->label('Invoice Number')
                            ->disabled()
                            ->visible(fn ($livewire) => $livewire instanceof EditRecord)
                            ->helperText('Auto-generated in format: #{CENTRE_CODE}/{YEAR}/{NUMBER}')
                            ->columnSpanFull(),

                        Section::make('Billing Information')
                            ->description('Select the centre and parent for this invoice.')
                            ->schema([
                                Select::make('centre_id')
                                    ->label('Centre')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (! $user->current_tenant_id) {
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
                                    ->native(false)
                                    ->columnSpanFull(),

                                Select::make('user_id')
                                    ->label('Parent')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (! $user->current_tenant_id) {
                                            return [];
                                        }

                                        return User::whereHas('tenants', function (Builder $query) use ($user) {
                                            $query->where('tenants.id', $user->current_tenant_id);
                                        })->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make('Date & Period')
                            ->description('Set billing period and payment deadline.')
                            ->schema([
                                DateTimePicker::make('date')
                                    ->label('Billing Month')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now())
                                    ->helperText('The month this invoice covers.'),

                                DateTimePicker::make('due_at')
                                    ->label('Due Date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y')
                                    ->default(now()->addDays(7))
                                    ->helperText('Payment deadline for this invoice.'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(2),

                Section::make('Status')
                    ->description('Current invoice status.')
                    ->schema([
                        Select::make('status')
                            ->label('Invoice Status')
                            ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                            ->required()
                            ->native(false)
                            ->default(InvoiceStatus::DRAFT->value)
                            ->helperText('Update status as invoice progresses.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),

                Section::make('E-Invoice Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('einvoice_uuid')
                                    ->label('E-Invoice UUID')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),

                                TextInput::make('einvoice_status')
                                    ->label('E-Invoice Status')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('einvoice_submission_id')
                                    ->label('Submission ID')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),

                                DateTimePicker::make('einvoice_submitted_at')
                                    ->label('Submitted At')
                                    ->disabled()
                                    ->visible(fn ($livewire) => $livewire instanceof EditRecord)
                                    ->displayFormat('M d, Y H:i'),
                            ]),

                        TextInput::make('einvoice_validation_url')
                            ->label('Validation URL')
                            ->disabled()
                            ->visible(fn ($livewire) => $livewire instanceof EditRecord)
                            ->url()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record && $record->einvoice_uuid)
                    ->collapsible()
                    ->collapsed(),

                Hidden::make('tenant_id')
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
                TextColumn::make('number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('user.name')
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

                TextColumn::make('date')
                    ->label('Billing Month')
                    ->date('M, Y')
                    ->sortable()
                    ->description(fn (Invoice $record): string => 'Due: '.$record->due_at->format('M d, Y')),

                TextColumn::make('status')
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

                TextColumn::make('einvoice_status')
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

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('total_discounts')
                    ->label('Discounts')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('total')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('balance')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->date(),
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
                    ->schema([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),

                    DownloadInvoicePdfAction::make(),

                    SubmitToEInvoiceAction::make(),

                    SendNotificationAction::make(),

                    MakePaymentAction::make(),

                    MarkAsPaidAction::make(),

                    EditAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('update', $record)),

                    DeleteAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('delete', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkPayInvoicesBulkAction::make(),
                    SubmitBulkToEInvoiceAction::make(),
                    SendBulkNotificationAction::make(),
                    ExportInvoicesAction::make(),
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Invoice::class)),
                ]),
            ]);
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
