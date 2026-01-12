<?php

namespace App\Filament\Admin\Resources\Centres;

use App\Filament\Admin\Resources\Campuses\Schemas\CampusForm;
use App\Filament\Admin\Resources\Centres\Pages\CreateCentre;
use App\Filament\Admin\Resources\Centres\Pages\EditCentre;
use App\Filament\Admin\Resources\Centres\Pages\ListCentres;
use App\Filament\Admin\Resources\Centres\RelationManagers\InvoicesRelationManager;
use App\Filament\Admin\Resources\Centres\RelationManagers\UsersRelationManager;
use App\Models\Campus;
use App\Models\Centre;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CentreResource extends Resource
{
    protected static ?string $model = Centre::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    public static function shouldCheckPolicyExistence(): bool
    {
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
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

    protected static string|\UnitEnum|null $navigationGroup = 'Campus Management';

    protected static ?string $modelLabel = 'Centre';

    protected static ?string $navigationLabel = 'Centres';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Centre Information')
                    ->tabs([
                        Tabs\Tab::make('Basic Details')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Section::make('Centre Information')
                                    ->description('Basic information about the centre')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Centre Name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('e.g., Main Campus Centre')
                                                    ->live(debounce: 2000)
                                                    ->afterStateUpdated(function (string $state, Set $set, Get $get) {
                                                        $set('slug', str()->slug($state));
                                                        $currentCode = $get('code');
                                                        if (empty($currentCode)) {
                                                            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($state, 0, 6)));
                                                            $set('code', $code);
                                                        }
                                                    })
                                                    ->suffixIcon('heroicon-m-building-storefront'),

                                                TextInput::make('code')
                                                    ->label('Centre Code')
                                                    ->required()
                                                    ->maxLength(10)
                                                    ->rule('alpha_num')
                                                    ->unique(ignoreRecord: true)
                                                    ->helperText('Unique alphanumeric code (auto-generated from name)')
                                                    ->placeholder('e.g., CTR001, MAIN')
                                                    ->suffixIcon('heroicon-m-hashtag'),

                                                TextInput::make('slug')
                                                    ->label('URL Slug')
                                                    ->readOnly()
                                                    ->dehydrated()
                                                    ->helperText('Auto-generated from centre name')
                                                    ->prefixIcon('heroicon-m-link'),

                                                Select::make('status')
                                                    ->label('Status')
                                                    ->options([
                                                        'active' => 'Active',
                                                        'inactive' => 'Inactive',
                                                    ])
                                                    ->required()
                                                    ->default('active')
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-m-signal'),
                                            ]),
                                    ]),

                                Section::make('Campus Assignment')
                                    ->description('Assign this centre to a campus')
                                    ->schema([
                                        Select::make('campus_id')
                                            ->label('Campus')
                                            ->relationship('campus', 'name')
                                            ->options(Campus::pluck('name', 'id')->toArray())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->native(false)
                                            ->prefixIcon('heroicon-m-building-office-2')
                                            ->helperText('Select the campus this centre belongs to, or create a new one')
                                            ->createOptionForm(CampusForm::getComponents())
                                            ->createOptionUsing(function (array $data): int {
                                                return Campus::create([...$data, 'tenant_id' => 10])->id;
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Contact Information')
                                    ->description('How to reach this centre')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('phone')
                                                    ->label('Phone Number')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->placeholder('+60312345678')
                                                    ->helperText('Primary contact number')
                                                    ->prefixIcon('heroicon-m-phone'),

                                                TextInput::make('email')
                                                    ->label('Email Address')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->placeholder('centre@example.com')
                                                    ->helperText('Primary contact email')
                                                    ->prefixIcon('heroicon-m-envelope'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Address')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Centre Address')
                                    ->description('Physical location of this centre')
                                    ->schema([
                                        TextInput::make('address_1')
                                            ->label('Address Line 1')
                                            ->maxLength(255)
                                            ->placeholder('123 Jalan Example')
                                            ->helperText('Street address')
                                            ->prefixIcon('heroicon-m-map-pin')
                                            ->columnSpanFull(),

                                        TextInput::make('address_2')
                                            ->label('Address Line 2')
                                            ->maxLength(255)
                                            ->placeholder('Taman Test, Block A (optional)')
                                            ->helperText('Additional address details')
                                            ->prefixIcon('heroicon-m-map-pin')
                                            ->columnSpanFull(),

                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('city')
                                                    ->label('City')
                                                    ->maxLength(255)
                                                    ->placeholder('Kuala Lumpur')
                                                    ->prefixIcon('heroicon-m-building-office'),

                                                TextInput::make('postal_code')
                                                    ->label('Postal Code')
                                                    ->maxLength(255)
                                                    ->placeholder('50000')
                                                    ->mask('99999')
                                                    ->prefixIcon('heroicon-m-map'),

                                                TextInput::make('state')
                                                    ->label('State')
                                                    ->maxLength(255)
                                                    ->placeholder('Selangor')
                                                    ->prefixIcon('heroicon-m-globe-alt'),
                                            ]),
                                    ])
                                    ->columns(1),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
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
                    ViewAction::make(),
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
