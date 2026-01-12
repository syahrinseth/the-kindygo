<?php

namespace App\Filament\Admin\Resources\Quotations\Tables;

use App\Actions\Quotation\ConvertQuotationToInvoice;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuotationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Quotation No.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('M d, Y')
                    ->sortable()
                    ->badge()
                    ->color(fn (Quotation $record): string => $record->isExpired() ? 'danger' : 'success'
                    ),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (QuotationStatus $state): string => match ($state) {
                        QuotationStatus::DRAFT => 'gray',
                        QuotationStatus::PENDING => 'warning',
                        QuotationStatus::ACCEPTED => 'success',
                        QuotationStatus::CONVERTED => 'info',
                        QuotationStatus::EXPIRED => 'danger',
                        QuotationStatus::REJECTED => 'danger',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Parent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('child.full_name')
                    ->label('Child')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('MYR', 100)
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('convertedInvoice.number')
                    ->label('Invoice')
                    ->url(fn (Quotation $record): ?string => $record->converted_invoice_id
                            ? route('filament.app.resources.invoices.view', $record->converted_invoice_id)
                            : null
                    )
                    ->color('primary')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(QuotationStatus::options())
                    ->multiple(),

                Filter::make('expired')
                    ->query(fn (Builder $query): Builder => $query->where('valid_until', '<', now())
                        ->whereNotIn('status', [
                            QuotationStatus::CONVERTED->value,
                            QuotationStatus::EXPIRED->value,
                            QuotationStatus::REJECTED->value,
                        ])
                    )
                    ->toggle(),

                SelectFilter::make('centre')
                    ->relationship('centre', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Quotation $record): string => route('quotation.download-pdf', $record)
                        )
                        ->openUrlInNewTab(),

                    EditAction::make(),

                    Action::make('convert_to_invoice')
                        ->label('Convert to Invoice')
                        ->icon('heroicon-o-document-text')
                        ->visible(fn (Quotation $record): bool => $record->status !== QuotationStatus::CONVERTED && ! $record->isExpired()
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Convert Quotation to Invoice')
                        ->modalDescription('Select which items to include in the invoice.')
                        ->form([
                            CheckboxList::make('selected_items')
                                ->label('Items to Convert')
                                ->options(fn (Quotation $record): array => $record->quotationItems->pluck('name', 'id')->toArray()
                                )
                                ->required()
                                ->columns(1),
                        ])
                        ->action(function (Quotation $record, array $data, ConvertQuotationToInvoice $converter): void {
                            $invoice = $converter->execute($record, $data['selected_items']);

                            Notification::make()
                                ->success()
                                ->title('Quotation Converted')
                                ->body("Invoice {$invoice->number} has been created.")
                                ->send();

                            redirect()->route('filament.app.resources.invoices.view', $invoice);
                        }),

                    DeleteAction::make()
                        ->visible(fn (Quotation $record): bool => $record->status === QuotationStatus::DRAFT
                        ),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ]);
    }
}
