<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CentreResource\Pages;
use App\Filament\Resources\CentreResource\RelationManagers;
use App\Models\Campus;
use App\Models\Centre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;

class CentreResource extends Resource
{
    protected static ?string $model = Centre::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Campus Management';

    protected static ?string $modelLabel = 'Centre';

    protected static ?string $navigationLabel = 'Centres';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 2000) // Wait 2 seconds after typing stops
                                    ->afterStateUpdated(function (string $state, Forms\Set $set) {
                                        $set('slug', str()->slug($state));
                                    }),
                                TextInput::make('slug')
                                    ->readOnly()
                                    ->dehydrated(),
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive'
                                    ])
                                    ->required()
                                    ->default('active'),
                                Select::make('campus_id')
                                    ->relationship('campus', 'name')
                                    ->options(Campus::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('address_1')
                                            ->label('Address Line 1')
                                            ->maxLength(255),
                                        TextInput::make('address_2')
                                            ->label('Address Line 2')
                                            ->maxLength(255),
                                        TextInput::make('postal_code')
                                            ->maxLength(255),
                                        TextInput::make('city')
                                            ->maxLength(255),
                                        TextInput::make('state')
                                            ->maxLength(255),
                                        Hidden::make('tenant_id')
                                            ->default(Filament::getTenant()->id),
                                    ])
                            ]),
                    ]),

                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                            TextInput::make('phone')
                                ->tel()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->email()
                                ->maxLength(255),
                            ]),
                    ]),

                Section::make('Address Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('address_1')
                                    ->label('Address Line 1')
                                    ->maxLength(255),
                                TextInput::make('address_2')
                                    ->label('Address Line 2')
                                    ->maxLength(255),
                                TextInput::make('postal_code')
                                    ->maxLength(255),
                                TextInput::make('city')
                                    ->maxLength(255),
                                TextInput::make('state')
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('campus.name')
                    ->searchable()
                    ->sortable()
                    ->label('Campus'),
                Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'pending' => 'Pending',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCentres::route('/'),
            'create' => Pages\CreateCentre::route('/create'),
            'edit' => Pages\EditCentre::route('/{record}/edit'),
        ];
    }
}
