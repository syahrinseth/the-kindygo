<?php

namespace App\Filament\Admin\Resources\Centres\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only allow viewing invoices if the user can view the owner record and has appropriate permissions
        return Auth::user()->can('view', $ownerRecord) &&
               Auth::user()->can('viewAny', Invoice::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Billing Month')
                    ->date('M, Y')
                    ->sortable(),

                TextColumn::make('due_at')
                    ->date('M d, Y')
                    ->sortable(),

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
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total')
                    ->money('MYR', 100)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),

                Filter::make('overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from'),
                        DatePicker::make('date_until'),
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
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn ($record) => Auth::user()->can('view', $record))
                    ->schema([
                        Section::make('Invoice Details')
                            ->schema([
                                TextEntry::make('number')
                                    ->label('Invoice Number')
                                    ->badge()
                                    ->color('primary'),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                                        InvoiceStatus::DRAFT => 'gray',
                                        InvoiceStatus::PENDING => 'warning',
                                        InvoiceStatus::PARTIALLY_PAID => 'info',
                                        InvoiceStatus::PAID => 'success',
                                        InvoiceStatus::OVERDUE => 'danger',
                                        InvoiceStatus::CANCELLED => 'gray',
                                    }),

                                TextEntry::make('date')
                                    ->label('Billing Month')
                                    ->date('M, Y'),

                                TextEntry::make('due_at')
                                    ->label('Due Date')
                                    ->date('M d, Y')
                                    ->color(function ($record) {
                                        if ($record->due_at && $record->due_at->isPast() && $record->status !== InvoiceStatus::PAID) {
                                            return 'danger';
                                        }

                                        return null;
                                    }),
                            ])->columns(2),

                        Section::make('Customer Information')
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Customer'),

                                TextEntry::make('user.email')
                                    ->label('Email'),

                                TextEntry::make('centre.name')
                                    ->label('Centre'),
                            ])->columns(2),

                        Section::make('Financial Summary')
                            ->schema([
                                TextEntry::make('total_items')
                                    ->label('Subtotal')
                                    ->money('MYR'),

                                TextEntry::make('total_discounts')
                                    ->label('Total Discounts')
                                    ->money('MYR')
                                    ->visible(fn ($record) => $record->total_discounts > 0),

                                TextEntry::make('total')
                                    ->label('Total Amount')
                                    ->money('MYR')
                                    ->weight('bold')
                                    ->size('lg'),

                                TextEntry::make('totalPaid')
                                    ->label('Amount Paid')
                                    ->money('MYR')
                                    ->state(fn ($record) => $record->getTotalPaid()),

                                TextEntry::make('remainingBalance')
                                    ->label('Outstanding Balance')
                                    ->money('MYR')
                                    ->state(fn ($record) => $record->getRemainingBalance())
                                    ->color(fn ($record) => $record->getRemainingBalance() > 0 ? 'warning' : 'success'),
                            ])->columns(2),

                        Section::make('Invoice Items')
                            ->schema([
                                RepeatableEntry::make('invoiceItems')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Item'),

                                        TextEntry::make('child.name')
                                            ->label('Child')
                                            ->visible(fn ($state) => $state !== null),

                                        TextEntry::make('quantity')
                                            ->label('Qty'),

                                        TextEntry::make('price')
                                            ->label('Unit Price')
                                            ->money('MYR'),

                                        TextEntry::make('discount')
                                            ->label('Discount')
                                            ->money('MYR')
                                            ->visible(fn ($state) => $state > 0),

                                        TextEntry::make('total')
                                            ->label('Total')
                                            ->money('MYR')
                                            ->weight('semibold'),
                                    ])
                                    ->columns(6)
                                    ->grid(6),
                            ]),

                        Section::make('E-Invoice Information')
                            ->schema([
                                TextEntry::make('einvoice_status')
                                    ->label('E-Invoice Status')
                                    ->badge()
                                    ->visible(fn ($record) => $record->einvoice_status !== null),

                                TextEntry::make('einvoice_uuid')
                                    ->label('E-Invoice UUID')
                                    ->copyable()
                                    ->visible(fn ($record) => $record->einvoice_uuid !== null),

                                TextEntry::make('einvoice_submitted_at')
                                    ->label('Submitted At')
                                    ->dateTime('M d, Y H:i')
                                    ->visible(fn ($record) => $record->einvoice_submitted_at !== null),

                                TextEntry::make('einvoice_validation_url')
                                    ->label('Validation URL')
                                    ->url(fn ($record) => $record->einvoice_validation_url)
                                    ->openUrlInNewTab()
                                    ->visible(fn ($record) => $record->einvoice_validation_url !== null),
                            ])
                            ->columns(2)
                            ->visible(fn ($record) => $record->isEInvoiceSubmitted()),
                    ]),
            ])
            ->toolbarActions([]);
    }
}
