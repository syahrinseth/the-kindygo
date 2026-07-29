<?php

namespace App\Filament\Parent\Resources;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Filament\Parent\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Parent\Resources\PaymentResource\Pages\ViewPayment;
use App\Filament\Parent\Resources\PaymentResource\RelationManagers\InvoicesRelationManager;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'My Payments';

    protected static ?int $navigationSort = 20;

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        return parent::getEloquentQuery()
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->current_tenant_id)
            ->with(['tenant', 'centres', 'user', 'invoices'])
            ->latest();
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user->can('viewAny', Payment::class) && $user->hasRole('Parent');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', Payment::class) && Auth::user()->hasRole('Parent');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
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

                TextColumn::make('invoices.number')
                    ->label('Invoice Number(s)')
                    ->badge()
                    ->separator(',')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::PAID => 'success',
                        PaymentStatus::FAILED => 'danger',
                        PaymentStatus::CANCELLED => 'gray',
                        PaymentStatus::REFUNDED => 'info',
                        PaymentStatus::PARTIALLY_PAID => 'info',
                        PaymentStatus::UNPAID => 'danger',
                    })
                    ->formatStateUsing(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::PENDING => 'Pending',
                        PaymentStatus::PAID => 'Paid',
                        PaymentStatus::FAILED => 'Failed',
                        PaymentStatus::CANCELLED => 'Cancelled',
                        PaymentStatus::REFUNDED => 'Refunded',
                        PaymentStatus::PARTIALLY_PAID => 'Partially Paid',
                        PaymentStatus::UNPAID => 'Unpaid',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gateway')
                    ->badge()
                    ->color(fn (Gateway $state): string => match ($state) {
                        Gateway::BANK_TRANSFER => 'blue',
                        Gateway::CHIP => 'green',
                        Gateway::CASH => 'gray',
                        Gateway::BILLPLZ => 'warning',
                        Gateway::STRIPE => 'purple',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('centres.name')
                    ->label('Centre(s)')
                    ->badge()
                    ->separator(',')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Payment Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
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
                        \Filament\Forms\Components\DatePicker::make('paid_from')
                            ->label('From'),
                        \Filament\Forms\Components\DatePicker::make('paid_until')
                            ->label('To'),
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
                    \Filament\Actions\ViewAction::make()
                        ->visible(fn (Payment $record) => Auth::user()->can('view', $record)),

                    Action::make('download_receipt')
                        ->label('Download Receipt')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->url(fn (Payment $record): string => route('payments.receipt.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Payment $record) => Auth::user()->can('view', $record) && $record->status === PaymentStatus::PAID),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
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
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
