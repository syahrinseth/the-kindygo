<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Invoice;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Enums\InvoiceStatus;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EInvoiceResource\Pages;

class EInvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'E-Invoices';

    protected static ?string $modelLabel = 'E-Invoice';

    protected static ?string $pluralModelLabel = 'E-Invoices';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['tenant', 'centre', 'user'])
            ->orderBy('created_at', 'desc');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Information')
                    ->schema([
                        Forms\Components\TextInput::make('number')
                            ->label('Invoice Number')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'overdue' => 'Overdue',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('total')
                            ->label('Total Amount')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => 'RM ' . number_format($state / 100, 2)),
                        
                        Forms\Components\DatePicker::make('date')
                            ->label('Invoice Date')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\DatePicker::make('due_at')
                            ->label('Due Date')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('E-Invoice Information')
                    ->schema([
                        Forms\Components\TextInput::make('einvoice_uuid')
                            ->label('E-Invoice UUID')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('einvoice_status')
                            ->label('E-Invoice Status')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\DateTimePicker::make('einvoice_submitted_at')
                            ->label('Submitted At')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Textarea::make('einvoice_validation_url')
                            ->label('Validation URL')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3),
                    ])
                    ->columns(2)
                    ->visible(fn (Model $record = null) => $record?->isEInvoiceSubmitted()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice #')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'RM ' . number_format($state / 100, 2))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('einvoice_status')
                    ->badge()
                    ->label('E-Invoice')
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state ?? 'Not Submitted'),
                
                Tables\Columns\TextColumn::make('einvoice_submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'overdue' => 'Overdue',
                        'cancelled' => 'Cancelled',
                    ]),
                
                Tables\Filters\SelectFilter::make('einvoice_status')
                    ->label('E-Invoice Status')
                    ->options([
                        'submitted' => 'Submitted',
                        'valid' => 'Valid',
                        'invalid' => 'Invalid',
                        'cancelled' => 'Cancelled',
                        'pending' => 'Pending',
                    ]),
                
                Tables\Filters\Filter::make('not_submitted')
                    ->label('Not Submitted to E-Invoice')
                    ->query(fn (Builder $query) => $query->whereNull('einvoice_uuid')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\Action::make('submit_einvoice')
                    ->label('Submit to E-Invoice')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Model $record) => !$record->isEInvoiceSubmitted())
                    ->requiresConfirmation()
                    ->modalHeading('Submit Invoice to LHDN E-Invoice System')
                    ->modalDescription('This will submit the invoice to the LHDN e-Invoice system. This action cannot be undone.')
                    ->action(function (Model $record) {
                        try {
                            $record->submitToEInvoice();
                            
                            Notification::make()
                                ->title('E-Invoice Submitted Successfully')
                                ->body("Invoice #{$record->number} has been submitted to LHDN.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('E-Invoice Submission Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                
                Tables\Actions\Action::make('refresh_status')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (Model $record) => $record->isEInvoiceSubmitted())
                    ->action(function (Model $record) {
                        try {
                            $record->refreshEInvoiceStatus();
                            
                            Notification::make()
                                ->title('Status Refreshed')
                                ->body("E-Invoice status has been updated.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to Refresh Status')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                
                Tables\Actions\Action::make('view_validation_url')
                    ->label('View Validation')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (Model $record) => !empty($record->getEInvoiceValidationUrl()))
                    ->url(fn (Model $record) => $record->getEInvoiceValidationUrl())
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('cancel_einvoice')
                    ->label('Cancel E-Invoice')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Model $record) => $record->isEInvoiceSubmitted() && $record->einvoice_status !== 'cancelled')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel E-Invoice')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3)
                            ->placeholder('Please provide a reason for cancelling this e-Invoice...')
                    ])
                    ->action(function (Model $record, array $data) {
                        try {
                            $record->cancelEInvoice($data['reason']);
                            
                            Notification::make()
                                ->title('E-Invoice Cancelled')
                                ->body("Invoice #{$record->number} has been cancelled.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to Cancel E-Invoice')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('submit_bulk_einvoice')
                    ->label('Submit to E-Invoice')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Submit Multiple Invoices to E-Invoice')
                    ->modalDescription('This will submit all selected invoices to the LHDN e-Invoice system.')
                    ->action(function ($records) {
                        $successful = 0;
                        $failed = 0;
                        
                        foreach ($records as $record) {
                            if (!$record->isEInvoiceSubmitted()) {
                                try {
                                    $record->submitToEInvoice();
                                    $successful++;
                                } catch (\Exception $e) {
                                    $failed++;
                                }
                            }
                        }
                        
                        if ($successful > 0) {
                            Notification::make()
                                ->title("E-Invoice Bulk Submission")
                                ->body("Successfully submitted {$successful} invoices. {$failed} failed.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("E-Invoice Bulk Submission Failed")
                                ->body("No invoices were submitted successfully.")
                                ->danger()
                                ->send();
                        }
                    }),
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
            'index' => Pages\ListEInvoices::route('/'),
            'view' => Pages\ViewEInvoice::route('/{record}'),
        ];
    }
}
