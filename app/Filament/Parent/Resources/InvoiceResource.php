<?php

namespace App\Filament\Parent\Resources;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Filament\Parent\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Parent\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
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

    protected static ?string $navigationLabel = 'My Invoices';

    protected static ?int $navigationSort = 10;

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()
            ->whereNotIn('status', [InvoiceStatus::PAID, InvoiceStatus::CANCELLED, InvoiceStatus::DRAFT])
            ->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdueCount = static::getEloquentQuery()->overdue()->count();

        return $overdueCount > 0 ? 'danger' : 'warning';
    }

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        return parent::getEloquentQuery()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                InvoiceStatus::PENDING,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::PAID,
                InvoiceStatus::OVERDUE,
            ])
            ->with([
                'tenant',
                'user',
                'user.userAddress',
                'centre',
                'payments' => function ($query) {
                    $query
                        ->where('status', PaymentStatus::PAID)
                        ->with(['user', 'media']);
                },
                'invoiceItems.child',
            ])
            ->latest('date');
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user->can('viewAny', Invoice::class) && $user->hasRole('parent');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', Invoice::class) && Auth::user()->hasRole('parent');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
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
                    ->sortable(),

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
                        InvoiceStatus::PARTIALLY_PAID => 'info',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    })
                    ->formatStateUsing(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'Draft',
                        InvoiceStatus::PENDING => 'Pending',
                        InvoiceStatus::PARTIALLY_PAID => 'Partially Paid',
                        InvoiceStatus::PAID => 'Paid',
                        InvoiceStatus::OVERDUE => 'Overdue',
                        InvoiceStatus::CANCELLED => 'Cancelled',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('MYR', 100)
                    ->sortable(),

                TextColumn::make('balance')
                    ->label('Amount Due')
                    ->money('MYR', 100)
                    ->sortable()
                    ->color(fn (Invoice $record): string => $record->balance > 0 ? 'danger' : 'success'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        InvoiceStatus::PENDING->value => 'Pending',
                        InvoiceStatus::PARTIALLY_PAID->value => 'Partially Paid',
                        InvoiceStatus::PAID->value => 'Paid',
                        InvoiceStatus::OVERDUE->value => 'Overdue',
                    ])
                    ->multiple(),

                Filter::make('overdue')
                    ->label('Overdue Only')
                    ->query(fn (Builder $query): Builder => $query->overdue()),

                SelectFilter::make('centre')
                    ->relationship('centre', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('From'),
                        DatePicker::make('date_until')
                            ->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    \Filament\Actions\ViewAction::make()
                        ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),

                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->url(fn (Invoice $record): string => route('invoice.download-pdf', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Invoice $record) => Auth::user()->can('view', $record)),

                    Action::make('make_payment')
                        ->label('Make Payment')
                        ->icon('heroicon-o-credit-card')
                        ->color('primary')
                        ->url(fn (Invoice $record): string => route('filament.parent.pages.make-payment', ['invoice' => $record->id]))
                        ->visible(fn (Invoice $record) => Auth::user()->can('view', $record) && $record->balance > 0),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
