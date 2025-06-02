<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Actions\InviteUserToTenantAction;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Filament\Resources\UserResource\RelationManagers\InvoicesRelationManager;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use App\Filament\Forms\UserForm;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

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
        $query = parent::getEloquentQuery();
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

    public static function form(Form $form): Form
    {
        return $form->schema(UserForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('centres.name')
                    ->badge()
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
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn (User $record) => Auth::user()->can('view', $record)),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn (User $record) => Auth::user()->can('update', $record)),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => Auth::user()->can('delete', $record)),
            ])
            ->headerActions([
                InviteUserToTenantAction::make()
                    ->visible(fn () => Auth::user()->can('inviteUsers', User::class)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
