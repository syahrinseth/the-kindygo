<?php

namespace App\Filament\Resources\Parents;

use App\Enums\NavigationGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Parents\RelationManagers\CentresRelationManager;
use App\Filament\Resources\Parents\RelationManagers\ChildrenRelationManager;
use App\Filament\Resources\Parents\Pages\ListParents;
use App\Filament\Resources\Parents\Pages\CreateParent;
use App\Filament\Resources\Parents\Pages\EditParent;
use App\Filament\Resources\ParentResource\Pages;
use App\Filament\Resources\ParentResource\RelationManagers;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Forms\UserForm;
use BackedEnum;
use UnitEnum;
use Filament\Schemas\Schema;

class ParentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::USER_MANAGEMENT->value;

    protected static ?string $navigationLabel = 'Parents';

    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

    protected static ?string $label = 'Parent';

    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', User::class);
    }

    public static function canCreate(): bool
    {
        // Only allow creating if user can create users AND has permission to manage parents
        return Auth::user()->can('create', User::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', User::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['profile', 'userAddress', 'officeInfo'])
            ->whereHas('roles', fn (Builder $query) =>
                $query->where('name', 'Parent')
            );

        $user = Auth::user();

        // Use policy to determine query scope
        if ($user->can('viewAllUsers', User::class)) {
            // Super Admin and Admin can see all parents in their context
            return $query;
        }

        // Principal and others see parents in their current centre only
        return $query->whereHas('centres', function (Builder $q) use ($user) {
            $q->whereIn('id', $user->centres()->pluck('id'));
        });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(UserForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('profile.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                TextColumn::make('profile.nric')
                    ->label('NRIC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                TextColumn::make('userAddress.city')
                    ->label('City')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                TextColumn::make('centres.name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                IconColumn::make('profile_complete')
                    ->label('Profile Complete')
                    ->boolean()
                    ->getStateUsing(function (User $record): bool {
                        return $record->profile &&
                               $record->userAddress &&
                               $record->userAddress->isComplete() &&
                               (!empty($record->profile->nric) || !empty($record->profile->passport));
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),
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
                SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (User $record) => Auth::user()->can('view', $record)),

                    EditAction::make()
                        ->visible(fn (User $record) => Auth::user()->can('update', $record)),

                    DeleteAction::make()
                        ->visible(fn (User $record) => Auth::user()->can('delete', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', User::class)),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CentresRelationManager::class,
            ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParents::route('/'),
            'create' => CreateParent::route('/create'),
            'edit' => EditParent::route('/{record}/edit'),
        ];
    }
}
