<?php

namespace App\Filament\Admin\Resources\LetterOfUndertakings;

use App\Filament\Admin\Resources\LetterOfUndertakings\Pages\CreateLetterOfUndertaking;
use App\Filament\Admin\Resources\LetterOfUndertakings\Pages\EditLetterOfUndertaking;
use App\Filament\Admin\Resources\LetterOfUndertakings\Pages\ListLetterOfUndertakings;
use App\Filament\Admin\Resources\LetterOfUndertakings\Schemas\LetterOfUndertakingForm;
use App\Filament\Admin\Resources\LetterOfUndertakings\Tables\LetterOfUndertakingsTable;
use App\Models\LetterOfUndertaking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LetterOfUndertakingResource extends Resource
{
    protected static ?string $model = LetterOfUndertaking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return LetterOfUndertakingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LetterOfUndertakingsTable::configure($table);
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
            'index' => ListLetterOfUndertakings::route('/'),
            'create' => CreateLetterOfUndertaking::route('/create'),
            'edit' => EditLetterOfUndertaking::route('/{record}/edit'),
        ];
    }
}
