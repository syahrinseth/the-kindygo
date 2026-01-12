<?php

namespace App\Filament\Admin\Resources\Campuses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CampusInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tenant.name')
                    ->label('Tenant'),
                TextEntry::make('name'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('email')
                    ->label('Email address')
                    ->placeholder('-'),
                TextEntry::make('address_1')
                    ->placeholder('-'),
                TextEntry::make('address_2')
                    ->placeholder('-'),
                TextEntry::make('postal_code')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                TextEntry::make('state')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
