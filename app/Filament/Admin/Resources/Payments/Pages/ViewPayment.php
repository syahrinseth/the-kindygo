<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Filament\Admin\Resources\Payments\Payments\PaymentResource;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('reference_no')
                                        ->label('Reference Number'),
                                    TextEntry::make('gateway')
                                        ->badge()
                                        ->color(fn ($state): string => match ($state->value) {
                                            'bank_transfer' => 'blue',
                                            'chip' => 'green',
                                            'cash' => 'gray',
                                            default => 'gray',
                                        }),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn ($state): string => match ($state->value) {
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'cancelled' => 'gray',
                                            'refunded' => 'info',
                                            default => 'gray',
                                        }),
                                ]),
                                Group::make([
                                    TextEntry::make('amount')
                                        ->label('Amount')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('paid_at')
                                        ->label('Payment Date')
                                        ->dateTime('M d, Y H:i'),
                                    TextEntry::make('gateway_payment_id')
                                        ->label('Gateway Payment ID')
                                        ->placeholder('Not available'),
                                ]),
                            ]),
                    ]),

                // CHIP Payment Details Section
                Section::make('CHIP Payment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('gateway_payment_id')
                                    ->label('CHIP Payment ID')
                                    ->placeholder('Not available'),
                                TextEntry::make('gateway_payment_data.chip_data.status')
                                    ->label('CHIP Status')
                                    ->formatStateUsing(function ($record) {
                                        return $record->getChipStatus() ?: 'N/A';
                                    })
                                    ->badge()
                                    ->color(function ($record): string {
                                        $status = $record->getChipStatus();

                                        return match ($status) {
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'cancelled' => 'gray',
                                            default => 'gray',
                                        };
                                    }),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('gateway_payment_data.chip_data.payment_method')
                                    ->label('Payment Method')
                                    ->formatStateUsing(function ($record) {
                                        return $record->getChipPaymentMethod() ?: 'N/A';
                                    })
                                    ->placeholder('Not available'),
                                TextEntry::make('gateway_payment_data.chip_data.client_email')
                                    ->label('Client Email')
                                    ->formatStateUsing(function ($record) {
                                        return $record->getChipClientEmail() ?: 'N/A';
                                    })
                                    ->placeholder('Not available'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('gateway_payment_data.chip_data.created_on')
                                    ->label('Created Date')
                                    ->formatStateUsing(function ($record) {
                                        $data = $record->gateway_payment_data;
                                        if (! is_array($data)) {
                                            return 'N/A';
                                        }

                                        // Check nested chip_data first, then fallback to root level
                                        $createdOn = $data['chip_data']['created_on'] ?? $data['created_on'] ?? null;
                                        if (! $createdOn) {
                                            return 'N/A';
                                        }

                                        try {
                                            return date('M d, Y H:i', strtotime($createdOn));
                                        } catch (Exception $e) {
                                            return 'Invalid date';
                                        }
                                    })
                                    ->placeholder('Not available'),
                                TextEntry::make('gateway_payment_data.chip_data.updated_on')
                                    ->label('Updated Date')
                                    ->formatStateUsing(function ($record) {
                                        $data = $record->gateway_payment_data;
                                        if (! is_array($data)) {
                                            return 'N/A';
                                        }

                                        // Check nested chip_data first, then fallback to root level
                                        $updatedOn = $data['chip_data']['updated_on'] ?? $data['updated_on'] ?? null;
                                        if (! $updatedOn) {
                                            return 'N/A';
                                        }

                                        try {
                                            return date('M d, Y H:i', strtotime($updatedOn));
                                        } catch (Exception $e) {
                                            return 'Invalid date';
                                        }
                                    })
                                    ->placeholder('Not available'),
                            ]),
                        TextEntry::make('gateway_payment_data.chip_data.total')
                            ->label('Purchase Total')
                            ->formatStateUsing(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data)) {
                                    return 'N/A';
                                }

                                // Check nested chip_data first, then fallback to purchase array
                                $total = $data['chip_data']['total'] ??
                                        $data['purchase']['total'] ?? null;
                                $currency = $data['chip_data']['currency'] ??
                                        $data['purchase']['currency'] ?? 'MYR';

                                if (! $total) {
                                    return 'N/A';
                                }

                                try {
                                    return $currency.' '.number_format($total / 100, 2);
                                } catch (Exception $e) {
                                    return 'Invalid amount';
                                }
                            })
                            ->placeholder('Not available'),
                        TextEntry::make('gateway_payment_data.chip_data.checkout_url')
                            ->label('Checkout URL')
                            ->formatStateUsing(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data)) {
                                    return 'N/A';
                                }

                                // Check nested chip_data first, then fallback to root level
                                $url = $data['chip_data']['checkout_url'] ?? $data['checkout_url'] ?? null;

                                return $url ?: 'N/A';
                            })
                            ->placeholder('Not available')
                            ->url(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data)) {
                                    return null;
                                }

                                return $data['chip_data']['checkout_url'] ?? $data['checkout_url'] ?? null;
                            })
                            ->openUrlInNewTab()
                            ->visible(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data)) {
                                    return false;
                                }

                                $url = $data['chip_data']['checkout_url'] ?? $data['checkout_url'] ?? null;

                                return ! empty($url);
                            }),

                        // Additional CHIP transaction details
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('gateway_payment_data.chip_data.transaction_id')
                                    ->label('Transaction ID')
                                    ->formatStateUsing(function ($record) {
                                        return $record->getChipTransactionId() ?: 'N/A';
                                    })
                                    ->placeholder('Not available')
                                    ->visible(function ($record) {
                                        return ! empty($record->getChipTransactionId());
                                    }),
                                TextEntry::make('gateway_payment_data.chip_data.bank_name')
                                    ->label('Bank Name')
                                    ->formatStateUsing(function ($record) {
                                        return $record->getChipBankName() ?: 'N/A';
                                    })
                                    ->placeholder('Not available')
                                    ->visible(function ($record) {
                                        return ! empty($record->getChipBankName());
                                    }),
                            ]),
                        TextEntry::make('gateway_payment_data.chip_data.reference')
                            ->label('CHIP Reference')
                            ->formatStateUsing(function ($record) {
                                return $record->getChipReference() ?: 'N/A';
                            })
                            ->placeholder('Not available')
                            ->visible(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data)) {
                                    return false;
                                }

                                return ! empty($data['chip_data']['reference']);
                            }),

                        // Additional callback information if available
                        TextEntry::make('gateway_payment_data.webhook_received_at')
                            ->label('Last Webhook')
                            ->formatStateUsing(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data) || ! isset($data['webhook_received_at'])) {
                                    return 'No webhook received';
                                }

                                try {
                                    return date('M d, Y H:i', strtotime($data['webhook_received_at']));
                                } catch (Exception $e) {
                                    return 'Invalid webhook date';
                                }
                            })
                            ->placeholder('No webhook received')
                            ->visible(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data)) {
                                    return false;
                                }

                                return ! empty($data['webhook_received_at']);
                            }),

                        TextEntry::make('gateway_payment_data.chip_data.success_callback_data.retrieved_at')
                            ->label('Success Callback')
                            ->formatStateUsing(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data) || ! isset($data['success_callback_data']) || ! is_array($data['success_callback_data'])) {
                                    return 'No success callback';
                                }
                                if (! isset($data['success_callback_data']['retrieved_at'])) {
                                    return 'No success callback';
                                }

                                try {
                                    return date('M d, Y H:i', strtotime($data['success_callback_data']['retrieved_at']));
                                } catch (Exception $e) {
                                    return 'Invalid callback date';
                                }
                            })
                            ->placeholder('No success callback')
                            ->visible(function ($record) {
                                $data = $record->gateway_payment_data;
                                if (! is_array($data) || ! isset($data['success_callback_data']) || ! is_array($data['success_callback_data'])) {
                                    return false;
                                }

                                return ! empty($data['success_callback_data']);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->gateway === Gateway::CHIP && ! empty($record->gateway_payment_data)),

                // Fallback for CHIP payments without detailed data
                Section::make('CHIP Payment Information')
                    ->schema([
                        TextEntry::make('gateway_payment_id')
                            ->label('CHIP Payment ID')
                            ->placeholder('Not available'),
                        TextEntry::make('gateway')
                            ->label('Note')
                            ->formatStateUsing(fn () => 'Detailed CHIP payment data not available. Use "php artisan payments:populate-chip-data" to retrieve payment details.')
                            ->color('warning'),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => $record->gateway === Gateway::CHIP && empty($record->gateway_payment_data) && ! empty($record->gateway_payment_id)),

                Section::make('User & Centre Information')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('User'),

                        RepeatableEntry::make('centres')
                            ->label('Centre(s) & Allocated Amounts')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Centre Name'),
                                        TextEntry::make('pivot.allocated_amount')
                                            ->label('Allocated Amount')
                                            ->money('MYR')
                                            ->formatStateUsing(fn ($state) => $state / 100),
                                    ]),
                            ])
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record->centres->count() > 0),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),

                        SpatieMediaLibraryImageEntry::make('payment_proof')
                            ->label('Payment Proof')
                            ->collection('payment_proof')
                            ->columnSpanFull()
                            ->visibility('private'),
                    ]),

                Section::make('Associated Invoices')
                    ->schema([
                        RepeatableEntry::make('invoices')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('number')
                                            ->label('Invoice Number'),
                                        TextEntry::make('pivot.amount')
                                            ->label('Payment Amount')
                                            ->money('MYR')
                                            ->formatStateUsing(fn ($state) => $state / 100),
                                        TextEntry::make('status')
                                            ->badge()
                                            ->color(fn ($state): string => match ($state->value) {
                                                'draft' => 'gray',
                                                'pending' => 'warning',
                                                'paid' => 'success',
                                                'overdue' => 'danger',
                                                'cancelled' => 'gray',
                                                default => 'gray',
                                            }),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record->invoices->count() > 0),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_receipt')
                ->label('Download Receipt')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (): string => route('payments.receipt.download', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => Auth::user()->can('view', $this->record) && $this->record->status === PaymentStatus::PAID),

            EditAction::make()
                ->visible(fn () => Auth::user()->can('update', $this->record)),
        ];
    }
}
