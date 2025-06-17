<?php

namespace App\Filament\Resources\EInvoiceResource\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\EInvoiceResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewEInvoice extends ViewRecord
{
    protected static string $resource = EInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submit_einvoice')
                ->label('Submit to E-Invoice')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => !$this->record->isEInvoiceSubmitted())
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->record->submitToEInvoice();
                        \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Invoice submitted to LHDN successfully!')
                                ->duration(5000)
                                ->send();
                        $this->refreshFormData(['einvoice_uuid', 'einvoice_status', 'einvoice_submitted_at', 'einvoice_validation_url']);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Failed to submit invoice:')
                                ->body($e->getMessage())
                                ->duration(5000)
                                ->send();
                    }
                }),

            Actions\Action::make('refresh_status')
                ->label('Refresh Status')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn () => $this->record->isEInvoiceSubmitted())
                ->action(function () {
                    try {
                        $this->record->refreshEInvoiceStatus();
                        \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('E-Invoice status refreshed successfully!')
                                ->duration(5000)
                                ->send();
                        $this->refreshFormData(['einvoice_status']);
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Failed to refresh status:')
                                ->body($e->getMessage())
                                ->duration(5000)
                                ->send();
                    }
                }),

            Actions\Action::make('view_validation')
                ->label('View Validation')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn () => !empty($this->record->getEInvoiceValidationUrl()))
                ->url(fn () => $this->record->getEInvoiceValidationUrl())
                ->openUrlInNewTab(),

            Actions\Action::make('preview_data')
                ->label('Preview E-Invoice Data')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->modalContent(function () {
                    try {
                        $data = $this->record->toEInvoiceFormat();
                        return view('filament.modals.einvoice-preview', ['data' => $data]);
                    } catch (\Exception $e) {
                        return view('filament.modals.error', ['message' => $e->getMessage()]);
                    }
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Invoice Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('number')
                            ->label('Invoice Number'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (InvoiceStatus $state): string => match ($state) {
                                InvoiceStatus::DRAFT => 'gray',
                                InvoiceStatus::PENDING => 'warning',
                                InvoiceStatus::PAID => 'success',
                                InvoiceStatus::OVERDUE => 'danger',
                                InvoiceStatus::CANCELLED => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('date')
                            ->date(),
                        Infolists\Components\TextEntry::make('due_at')
                            ->label('Due Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Total Amount')
                            ->formatStateUsing(fn ($state) => 'RM ' . number_format($state / 100, 2)),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Customer Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Customer Name'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('user.phone')
                            ->label('Phone'),
                        Infolists\Components\TextEntry::make('centre.name')
                            ->label('Centre'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('E-Invoice Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('einvoice_uuid')
                            ->label('E-Invoice UUID')
                            ->placeholder('Not submitted')
                            ->copyable()
                            ->copyMessage('UUID copied to clipboard'),
                        Infolists\Components\TextEntry::make('einvoice_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'submitted' => 'warning',
                                'pending' => 'info',
                                'valid' => 'success',
                                'invalid' => 'danger',
                                'cancelled' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => $state ?? 'Not Submitted'),
                        Infolists\Components\TextEntry::make('einvoice_submitted_at')
                            ->label('Submitted At')
                            ->dateTime()
                            ->placeholder('Not submitted'),
                        Infolists\Components\TextEntry::make('einvoice_validation_url')
                            ->label('Validation URL')
                            ->placeholder('Not available')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab()
                            ->copyable()
                            ->copyMessage('URL copied to clipboard'),
                    ])
                    ->columns(2)
                    ->visible(fn () => $this->record->isEInvoiceSubmitted()),
            ]);
    }
}
