<?php

namespace App\Filament\Resources\ChildEnrollmentResource\Pages;

use App\Filament\Resources\ChildEnrollmentResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewChildEnrollment extends ViewRecord
{
    protected static string $resource = ChildEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Enrollment Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('child.full_name')
                            ->label('Child'),
                        Infolists\Components\TextEntry::make('centre.name')
                            ->label('Centre'),
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Product'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'inactive' => 'gray',
                                'completed' => 'info',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('type')
                            ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value))),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Schedule & Billing')
                    ->schema([
                        Infolists\Components\TextEntry::make('date_start')
                            ->label('Start Date')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('date_end')
                            ->label('End Date')
                            ->dateTime()
                            ->placeholder('Ongoing'),
                        Infolists\Components\TextEntry::make('billed_every')
                            ->label('Billing Frequency')
                            ->formatStateUsing(fn ($state): string => ucwords(str_replace('_', ' ', $state->value))),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Currently Active')
                            ->boolean()
                            ->getStateUsing(fn ($record): bool => $record->isActive()),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('Additional Products')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('additional_products')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_id')
                                    ->label('Product')
                                    ->formatStateUsing(function ($state): string {
                                        if (!$state) return 'N/A';
                                        $product = Product::find($state);
                                        return $product ? $product->name : 'Product not found';
                                    }),
                                Infolists\Components\TextEntry::make('billed_every')
                                    ->label('Billing Frequency')
                                    ->formatStateUsing(fn ($state): string => $state ? ucwords(str_replace('_', ' ', $state)) : 'N/A'),
                                Infolists\Components\TextEntry::make('date_start')
                                    ->label('Start Date')
                                    ->formatStateUsing(fn ($state): string => $state ? \Carbon\Carbon::parse($state)->format('M j, Y g:i A') : 'N/A'),
                                Infolists\Components\TextEntry::make('date_end')
                                    ->label('End Date')
                                    ->formatStateUsing(fn ($state): string => $state ? \Carbon\Carbon::parse($state)->format('M j, Y g:i A') : 'Ongoing'),
                                Infolists\Components\TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('No notes')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                    ])
                    ->visible(fn ($record): bool => !empty($record->additional_products))
                    ->collapsible(),
                    
                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ])->columns(2),
            ]);
    }
}
