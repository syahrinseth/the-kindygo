<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Enums\Gateway;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Actions\MakePaymentAction;
use App\Filament\Resources\InvoiceResource\Actions\DownloadInvoicePdfAction;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Invoice Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('number')
                                        ->label('Invoice Number'),
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
                                Group::make([
                                    TextEntry::make('date')
                                        ->label('Billing Month')
                                        ->date('M d, Y'),
                                    TextEntry::make('due_at')
                                        ->date('M d, Y'),
                                ]),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('user.name')
                                        ->label('Client'),
                                ]),
                                Group::make([
                                    TextEntry::make('centre.name')
                                        ->label('Centre'),
                                ]),
                            ]),
                    ]),

                Section::make('Financial Summary')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Group::make([
                                    TextEntry::make('total_amount')
                                        ->label('Amount')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('total_discounts')
                                        ->label('Discounts')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                ]),
                                Group::make([
                                    TextEntry::make('total')
                                        ->label('Total Due')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state) => $state / 100),
                                    TextEntry::make('total')
                                        ->label('Total Paid')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state, Invoice $record) => $record->getTotalPaid() / 100),
                                    TextEntry::make('total')
                                        ->label('Remaining Balance')
                                        ->money('MYR')
                                        ->formatStateUsing(fn ($state, Invoice $record) => $record->getRemainingBalance() / 100),
                                ]),
                            ]),
                    ]),

                Section::make('Payment History')
                    ->visible(fn (Invoice $record) => $record->payments->count() > 0)
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Group::make([
                                            Grid::make(4)
                                                ->schema([
                                                    TextEntry::make('reference_no')
                                                        ->label('Reference'),
                                                    TextEntry::make('gateway')
                                                        ->label('Gateway')
                                                        ->formatStateUsing(fn (Gateway $state): string => match ($state?->value) {
                                                            'cash' => 'Cash',
                                                            'bank_transfer' => 'Bank Transfer',
                                                            'chip' => 'CHIP',
                                                            default => $state->value,
                                                        }),
                                                    TextEntry::make('status')
                                                        ->label('Status')
                                                        ->badge()
                                                        ->color(fn ($state): string => match ($state->value) {
                                                            'pending' => 'warning',
                                                            'paid' => 'success',
                                                            'failed' => 'danger',
                                                            'cancelled' => 'gray',
                                                            'refunded' => 'info',
                                                            default => 'gray',
                                                        }),
                                                    TextEntry::make('pivot.amount')
                                                        ->label('Amount')
                                                        ->money('MYR')
                                                        ->formatStateUsing(fn ($state) => $state / 100),
                                                ]),
                                            Grid::make(2)
                                                ->schema([
                                                    TextEntry::make('user.name')
                                                        ->label('Paid By'),
                                                    TextEntry::make('paid_at')
                                                        ->label('Payment Date')
                                                        ->date('M d, Y H:i')
                                                        ->placeholder('Not paid yet'),
                                                ]),
                                            TextEntry::make('description')
                                                ->label('Description')
                                                ->placeholder('No description')
                                                ->columnSpanFull(),
                                        ]),
                                        Group::make([
                                            SpatieMediaLibraryImageEntry::make('payment_proof')
                                                ->label('Payment Proof')
                                                ->collection('payment_proof'),
                                        ])->visible(fn ($record) => $record->gateway === Gateway::BANK_TRANSFER),
                                        
                                        // CHIP Payment Data Section
                                        Group::make([
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
                                                                    if (!is_array($data)) return 'N/A';
                                                                    
                                                                    // Check nested chip_data first, then fallback to root level
                                                                    $createdOn = $data['chip_data']['created_on'] ?? $data['created_on'] ?? null;
                                                                    if (!$createdOn) return 'N/A';
                                                                    
                                                                    try {
                                                                        return date('M d, Y H:i', strtotime($createdOn));
                                                                    } catch (\Exception $e) {
                                                                        return 'Invalid date';
                                                                    }
                                                                })
                                                                ->placeholder('Not available'),
                                                            TextEntry::make('gateway_payment_data.chip_data.updated_on')
                                                                ->label('Updated Date')
                                                                ->formatStateUsing(function ($record) {
                                                                    $data = $record->gateway_payment_data;
                                                                    if (!is_array($data)) return 'N/A';
                                                                    
                                                                    // Check nested chip_data first, then fallback to root level
                                                                    $updatedOn = $data['chip_data']['updated_on'] ?? $data['updated_on'] ?? null;
                                                                    if (!$updatedOn) return 'N/A';
                                                                    
                                                                    try {
                                                                        return date('M d, Y H:i', strtotime($updatedOn));
                                                                    } catch (\Exception $e) {
                                                                        return 'Invalid date';
                                                                    }
                                                                })
                                                                ->placeholder('Not available'),
                                                        ]),
                                                    TextEntry::make('gateway_payment_data.chip_data.total')
                                                        ->label('Purchase Total')
                                                        ->formatStateUsing(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data)) return 'N/A';
                                                            
                                                            // Check nested chip_data first, then fallback to purchase array
                                                            $total = $data['chip_data']['total'] ?? 
                                                                    $data['purchase']['total'] ?? null;
                                                            $currency = $data['chip_data']['currency'] ?? 
                                                                    $data['purchase']['currency'] ?? 'MYR';
                                                            
                                                            if (!$total) return 'N/A';
                                                            
                                                            try {
                                                                return $currency . ' ' . number_format($total / 100, 2);
                                                            } catch (\Exception $e) {
                                                                return 'Invalid amount';
                                                            }
                                                        })
                                                        ->placeholder('Not available'),
                                                    TextEntry::make('gateway_payment_data.chip_data.checkout_url')
                                                        ->label('Checkout URL')
                                                        ->formatStateUsing(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data)) return 'N/A';
                                                            
                                                            // Check nested chip_data first, then fallback to root level
                                                            $url = $data['chip_data']['checkout_url'] ?? $data['checkout_url'] ?? null;
                                                            return $url ?: 'N/A';
                                                        })
                                                        ->placeholder('Not available')
                                                        ->url(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data)) return null;
                                                            
                                                            return $data['chip_data']['checkout_url'] ?? $data['checkout_url'] ?? null;
                                                        })
                                                        ->openUrlInNewTab()
                                                        ->visible(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data)) return false;
                                                            
                                                            $url = $data['chip_data']['checkout_url'] ?? $data['checkout_url'] ?? null;
                                                            return !empty($url);
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
                                                                    return !empty($record->getChipTransactionId());
                                                                }),
                                                            TextEntry::make('gateway_payment_data.chip_data.bank_name')
                                                                ->label('Bank Name')
                                                                ->formatStateUsing(function ($record) {
                                                                    return $record->getChipBankName() ?: 'N/A';
                                                                })
                                                                ->placeholder('Not available')
                                                                ->visible(function ($record) {
                                                                    return !empty($record->getChipBankName());
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
                                                            if (!is_array($data)) return false;
                                                            
                                                            return !empty($data['chip_data']['reference']);
                                                        }),
                                    
                                                    // Additional callback information if available
                                                    TextEntry::make('gateway_payment_data.last_api_fetch')
                                                        ->label('Last Webhook')
                                                        ->formatStateUsing(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data) || !isset($data['webhook_received_at'])) {
                                                                return 'No webhook received';
                                                            }
                                                            
                                                            try {
                                                                return date('M d, Y H:i', strtotime($data['webhook_received_at']));
                                                            } catch (\Exception $e) {
                                                                return 'Invalid webhook date';
                                                            }
                                                        })
                                                        ->placeholder('No webhook received')
                                                        ->visible(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data)) return false;
                                                            return !empty($data['webhook_received_at']);
                                                        }),
                                                    
                                                    TextEntry::make('gateway_payment_data.success_callback_data')
                                                        ->label('Success Callback')
                                                        ->formatStateUsing(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data) || !isset($data['success_callback_data']) || !is_array($data['success_callback_data'])) {
                                                                return 'No success callback';
                                                            }
                                                            if (!isset($data['success_callback_data']['retrieved_at'])) {
                                                                return 'No success callback';
                                                            }
                                                            
                                                            try {
                                                                return date('M d, Y H:i', strtotime($data['success_callback_data']['retrieved_at']));
                                                            } catch (\Exception $e) {
                                                                return 'Invalid callback date';
                                                            }
                                                        })
                                                        ->placeholder('No success callback')
                                                        ->visible(function ($record) {
                                                            $data = $record->gateway_payment_data;
                                                            if (!is_array($data) || !isset($data['success_callback_data']) || !is_array($data['success_callback_data'])) {
                                                                return false;
                                                            }
                                                            return !empty($data['success_callback_data']);
                                                        }),
                                                ])
                                                ->collapsible()
                                                ->collapsed()
                                        ])->visible(fn ($record) => $record->gateway === Gateway::CHIP && !empty($record->gateway_payment_data)),
                                        
                                        // Fallback for CHIP payments without detailed data
                                        Group::make([
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
                                        ])->visible(fn ($record) => $record->gateway === Gateway::CHIP && empty($record->gateway_payment_data) && !empty($record->gateway_payment_id)),
                                    ]),
                            ])
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DownloadInvoicePdfAction::makeHeaderAction(),
            
            MakePaymentAction::makeHeaderAction(),
            
            Actions\EditAction::make()
                ->visible(fn (Invoice $record) => Auth::user()->can('update', $record)),
        ];
    }
}
