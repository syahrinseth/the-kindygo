<?php

namespace App\Filament\Resources\ChildEnrolmentResource\Pages;

use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Carbon\Carbon;
use App\Filament\Resources\ChildEnrolmentResource;
use App\Models\Product;
use App\Services\ChildEnrolmentInvoiceService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewChildEnrolment extends ViewRecord
{
    protected static string $resource = ChildEnrolmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('generate_invoices')
                ->label('Generate Invoice')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->action(function () {
                    $invoiceService = app(ChildEnrolmentInvoiceService::class);
                    $enrolments = $invoiceService->getRelatedEnrolments($this->record);
                    if (empty($enrolments)) {
                        Notification::make()
                            ->title('No Invoices Needed')
                            ->body('All enrolments for this parent at this centre already have current invoices.')
                            ->warning()
                            ->send();
                        return;
                    }
                    $invoices = $invoiceService->generateInvoicesForEnrolments($enrolments);

                    $childNames = $enrolments->map(fn($e) => $e->child->full_name)->unique()->implode(', ');

                    Notification::make()
                        ->title('Invoices Generated')
                        ->body("Successfully generated {$invoices->count()} invoice(s) for: {$childNames}.")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Generate Invoice')
                ->modalDescription('This will create a new invoice for this enrolment. Are you sure you want to proceed?')
                ->modalSubmitActionLabel('Generate Invoice')
                ->visible(fn(): bool => Auth::user()->can('update', $this->record)),
            // Actions\DeleteAction::make(), // temp disabled
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enrolment Information')
                    ->schema([
                        TextEntry::make('child.full_name')
                            ->label('Child'),
                        TextEntry::make('centre.name')
                            ->label('Centre'),
                        TextEntry::make('product.name')
                            ->label('Product'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn($state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'inactive' => 'gray',
                                'completed' => 'info',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('type')
                            ->formatStateUsing(fn($state): string => ucwords(str_replace('_', ' ', $state->value))),
                    ])->columns(2),

                Section::make('Schedule & Billing')
                    ->schema([
                        TextEntry::make('date_start')
                            ->label('Start Date')
                            ->dateTime(),
                        TextEntry::make('date_end')
                            ->label('End Date')
                            ->dateTime()
                            ->placeholder('Ongoing'),
                        TextEntry::make('billed_every')
                            ->label('Billing Frequency')
                            ->formatStateUsing(fn($state): string => ucwords(str_replace('_', ' ', $state->value))),
                        IconEntry::make('is_active')
                            ->label('Currently Active')
                            ->boolean()
                            ->getStateUsing(fn($record): bool => $record->isActive()),
                    ])->columns(2),

                Section::make('Additional Products')
                    ->schema([
                        RepeatableEntry::make('additional_products')
                            ->label('')
                            ->schema([
                                TextEntry::make('product_id')
                                    ->label('Product')
                                    ->formatStateUsing(function ($state): string {
                                        if (!$state) return 'N/A';
                                        $product = Product::find($state);
                                        return $product ? $product->name : 'Product not found';
                                    }),
                                TextEntry::make('billed_every')
                                    ->label('Billing Frequency')
                                    ->formatStateUsing(fn($state): string => $state ? ucwords(str_replace('_', ' ', $state)) : 'N/A'),
                                TextEntry::make('date_start')
                                    ->label('Start Date')
                                    ->formatStateUsing(fn($state): string => $state ? Carbon::parse($state)->format('M j, Y g:i A') : 'N/A'),
                                TextEntry::make('date_end')
                                    ->label('End Date')
                                    ->formatStateUsing(fn($state): string => $state ? Carbon::parse($state)->format('M j, Y g:i A') : 'Ongoing'),
                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('No notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                    ])
                    ->visible(fn($record): bool => !empty($record->additional_products))
                    ->collapsible(),

                Section::make('Timestamps')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])->columns(2),
            ]);
    }
}
