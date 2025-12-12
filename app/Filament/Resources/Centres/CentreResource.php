<?php

namespace App\Filament\Resources\Centres;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Centres\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Centres\Pages\ListCentres;
use App\Filament\Resources\Centres\Pages\CreateCentre;
use App\Filament\Resources\Centres\Pages\EditCentre;
use App\Filament\Resources\CentreResource\Pages;
use App\Filament\Resources\CentreResource\RelationManagers;
use App\Filament\Resources\Centres\RelationManagers\InvoicesRelationManager;
use App\Models\Campus;
use App\Models\Centre;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class CentreResource extends Resource
{
    protected static ?string $model = Centre::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forCurrentUser();
    }

    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', Centre::class);
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('create', Centre::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', Centre::class);
    }

    protected static string | \UnitEnum | null $navigationGroup = 'Campus Management';

    protected static ?string $modelLabel = 'Centre';

    protected static ?string $navigationLabel = 'Centres';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(debounce: 2000) // Wait 2 seconds after typing stops
                                    ->afterStateUpdated(function (string $state, Set $set, Get $get) {
                                        $set('slug', str()->slug($state));
                                        // Auto-generate code from name if not set
                                        $currentCode = $get('code');
                                        if (empty($currentCode)) {
                                            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($state, 0, 6)));
                                            $set('code', $code);
                                        }
                                    }),
                                TextInput::make('code')
                                    ->label('Centre Code')
                                    ->required()
                                    ->maxLength(10)
                                    ->rule('alpha_num')
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Short unique code for this centre (e.g., CTR001, MAIN, etc.)')
                                    ->placeholder('e.g., CTR001'),
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
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('campus.name')
                    ->searchable()
                    ->sortable()
                    ->label('Campus'),
                SelectColumn::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'pending' => 'Pending',
                    ])
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCentres::route('/'),
            'create' => CreateCentre::route('/create'),
            'edit' => EditCentre::route('/{record}/edit'),
        ];
    }
}
