<?php

namespace App\Filament\Admin\Resources\Payments\Payments;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Payments\Pages\CreateMultiInvoicePayment;
use App\Filament\Admin\Resources\Payments\Pages\CreatePayment;
use App\Filament\Admin\Resources\Payments\Pages\EditPayment;
use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\Pages\ViewPayment;
use App\Filament\Admin\Resources\Payments\RelationManagers\InvoicesRelationManager;
use App\Models\Centre;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('status', PaymentStatus::PENDING)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getEloquentQuery()->where('status', PaymentStatus::FAILED)->count() > 0
            ? 'danger'
            : 'warning';
    }

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['tenant', 'centres', 'user', 'invoices']);

        $user = Auth::user();
        if (! $user || ! $user->current_tenant_id) {
            return $query->whereRaw('1 = 0'); // Return empty result if no user or tenant
        }

        // Apply tenant filtering first
        $query->where('tenant_id', $user->current_tenant_id);

        // Apply role-based filtering
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            // Super Admin and Admin can view all payments in their tenant
            return $query;
        }

        if ($user->hasRole('Principal')) {
            // Principal can only view payments for their centres or payments without a specific centre
            $userCentreIds = $user->centres()
                ->where('centres.tenant_id', $user->current_tenant_id)
                ->pluck('centres.id');

            return $query->where(function ($q) use ($userCentreIds) {
                $q->whereDoesntHave('centres')
                    ->orWhereHas('centres', function ($centreQuery) use ($userCentreIds) {
                        $centreQuery->whereIn('centres.id', $userCentreIds);
                    });
            });
        }

        if ($user->hasRole('Parent')) {
            // Parents can only view payments directly related to them
            return $query->where(function ($q) use ($user) {
                // Direct payments where user_id matches
                $q->where('user_id', $user->id)
                  // Or payments related to their children through invoices
                    ->orWhereHas('invoices', function ($invoiceQuery) use ($user) {
                        $invoiceQuery->where('user_id', $user->id);
                    });
            });
        }

        // For other roles (Teacher, etc.), return empty result
        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', Payment::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', Payment::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('gateway')
                                    ->options(collect(Gateway::cases())->pluck('value', 'value')->toArray())
                                    ->required()
                                    ->native(false)
                                    ->default(Gateway::BANK_TRANSFER->value)
                                    ->disabled(fn ($livewire) => $livewire instanceof EditRecord)
                                    ->live(),

                                Select::make('status')
                                    ->options(collect(PaymentStatus::cases())->pluck('value', 'value')->toArray())
                                    ->required()
                                    ->native(false)
                                    ->default(PaymentStatus::PENDING->value)
                                    ->disabled(fn (Get $get) => $get('gateway') === Gateway::CHIP->value),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('reference_no')
                                    ->label('Reference Number')
                                    ->required()
                                    ->maxLength(255)
                                    ->disabled(fn (Get $get) => $get('gateway') === Gateway::CHIP->value),

                                TextInput::make('gateway_payment_id')
                                    ->label('Gateway Payment ID')
                                    ->maxLength(255)
                                    ->helperText('Payment ID from the payment gateway')
                                    ->disabled(fn ($livewire) => $livewire instanceof EditRecord),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount (in cents)')
                                    ->required()
                                    ->numeric()
                                    ->suffix('cents')
                                    ->helperText('Enter amount in cents (e.g., 10000 for RM100.00)')
                                    ->disabled(fn (Get $get) => $get('gateway') === Gateway::CHIP->value),

                                DateTimePicker::make('paid_at')
                                    ->label('Payment Date')
                                    ->native(false)
                                    ->displayFormat('M d, Y H:i')
                                    ->default(now()),
                            ]),

                        Grid::make(1)
                            ->schema([
                                Select::make('user_id')
                                    ->label('User')
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
                                    ->native(false),
                            ]),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        SpatieMediaLibraryFileUpload::make('payment_proof')
                            ->label('Payment Proof')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                            ->collection('payment_proof')
                            ->disk('private')
                            ->maxSize(5120) // 5MB
                            ->columnSpanFull()
                            ->helperText('Upload payment receipt or proof (JPEG, PNG, or PDF, max 5MB)'),
                    ]),

                Hidden::make('tenant_id')
                    ->default(function () {
                        return Auth::user()->current_tenant_id;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Reference No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('centres.name')
                    ->label('Centre(s)')
                    ->badge()
                    ->separator(',')
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn (Payment $record): ?string => $record->isMultiCentre()
                        ? 'Multi-centre payment: '.$record->centres->pluck('name')->join(', ')
                        : null),

                TextColumn::make('gateway')
                    ->badge()
                    ->color(fn (Gateway $state): string => match ($state) {
                        Gateway::BANK_TRANSFER => 'blue',
                        Gateway::CHIP => 'green',
                        Gateway::CASH => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::PAID => 'success',
                        PaymentStatus::FAILED => 'danger',
                        PaymentStatus::CANCELLED => 'gray',
                        PaymentStatus::REFUNDED => 'info',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Payment Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(PaymentStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),

                SelectFilter::make('gateway')
                    ->options(collect(Gateway::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),

                SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('paid_at')
                    ->schema([
                        DatePicker::make('paid_from'),
                        DatePicker::make('paid_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '>=', $date),
                            )
                            ->when(
                                $data['paid_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (Payment $record) => Auth::user()->can('view', $record)),

                    Action::make('download_receipt')
                        ->label('Download Receipt')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->url(fn (Payment $record): string => route('payments.receipt.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Payment $record) => Auth::user()->can('view', $record) && $record->status === PaymentStatus::PAID),

                    EditAction::make()
                        ->visible(fn (Payment $record) => Auth::user()->can('update', $record)),

                    DeleteAction::make()
                        ->visible(fn (Payment $record) => Auth::user()->can('delete', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Payment::class)),

                    BulkAction::make('download_first_receipt')
                        ->label('Download Receipt')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $paidRecord = $records->filter(fn (Payment $record) => $record->status === PaymentStatus::PAID)->first();

                            if (! $paidRecord) {
                                Notification::make()
                                    ->title('No paid payments selected')
                                    ->body('Only paid payments can have receipts downloaded.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            return redirect()->route('payments.receipt.download', $paidRecord);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Download Payment Receipt')
                        ->modalDescription('This will download a PDF receipt for the first selected paid payment.')
                        ->visible(fn () => Auth::user()->can('viewAny', Payment::class)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'create-multi' => CreateMultiInvoicePayment::route('/create-multi'),
            'view' => ViewPayment::route('/{record}'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }
}
