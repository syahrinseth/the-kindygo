<?php

namespace App\Filament\Resources\ChildEnrollmentResource\Pages;

use App\Filament\Resources\ChildEnrollmentResource;
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
