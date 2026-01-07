<?php

namespace App\Filament\Resources\Users;

use UnitEnum;
use BackedEnum;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Actions;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Filament\Forms\UserForm;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use App\Models\Scopes\BelongsToManyTenantScope;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\CreateUser;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\UserResource\RelationManagers;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use App\Filament\Resources\Users\Actions\InviteUserToTenantAction;
use App\Filament\Resources\Users\RelationManagers\InvoicesRelationManager;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'User Management';

    protected static ?string $tenantOwnershipRelationshipName = 'tenants';

    // Use policy to check if user can view any users
    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', User::class);
    }

    // Use policy to check if user can create users
    public static function canCreate(): bool
    {
        return Auth::user()->can('create', User::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->belongsToCurrentTenant()
            ->with(['profile', 'userAddress', 'officeInfo']);
        $user = Auth::user();

        // Use policy to determine query scope
        if ($user->can('viewAllUsers', User::class)) {
            // Super Admin and Admin can see all users in their context
            return $query;
        } else {
            // Principal and others see users in their current centre only
            return $query->whereHas('centres', function (Builder $query) use ($user) {
                $query->whereIn('id', $user->centres()->pluck('id'));
            });
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('viewAny', User::class);
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
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Photo')
                    ->collection('photo')
                    ->conversion('thumb')
                    ->height(50)
                    ->width(50)
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.svg'))
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('centres.name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('profile.nric')
                    ->label('NRIC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                TextColumn::make('profile.passport')
                    ->label('Passport')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                TextColumn::make('profile.tin')
                    ->label('TIN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set')
                    ->formatStateUsing(function (?string $state): string {
                        return $state ? '***' . substr($state, -4) : 'Not set';
                    })
                    ->tooltip(function (?string $state): string {
                        return $state ? 'TIN: ' . $state : 'TIN not provided';
                    }),
                TextColumn::make('profile.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                TextColumn::make('userAddress.city')
                    ->label('City')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                IconColumn::make('einvoice_ready')
                    ->label('E-Invoice Ready')
                    ->boolean()
                    ->getStateUsing(function (User $record): bool {
                        return $record->eInvoiceReady();
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(function (User $record): string {
                        if ($record->eInvoiceReady()) {
                            return 'Customer has complete e-Invoice information';
                        }

                        $missing = $record->getEInvoiceMissingRequirements();
                        return 'Missing: ' . implode(', ', $missing);
                    })
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
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->multiple()
                    ->preload(),
                Filter::make('einvoice_ready')
                    ->label('E-Invoice Ready')
                    ->query(fn (Builder $query): Builder =>
                        $query->eInvoiceReady()
                    )
                    ->toggle(),
                Filter::make('missing_identification')
                    ->label('Missing ID (NRIC/Passport)')
                    ->query(fn (Builder $query): Builder =>
                        $query->missingIdentification()
                    )
                    ->toggle(),
                Filter::make('missing_tin')
                    ->label('Missing TIN')
                    ->query(fn (Builder $query): Builder =>
                        $query->missingTin()
                    )
                    ->toggle(),
                Filter::make('missing_address')
                    ->label('Missing Complete Address')
                    ->query(fn (Builder $query): Builder =>
                        $query->missingCompleteAddress()
                    )
                    ->toggle(),
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
            ->headerActions([
                InviteUserToTenantAction::make()
                    ->visible(fn () => Auth::user()->can('inviteUsers', User::class)),
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
            InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
