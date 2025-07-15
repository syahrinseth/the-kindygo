<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParentResource\Pages;
use App\Filament\Resources\ParentResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Forms\UserForm;

class ParentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'User Management';

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
                Tables\Columns\TextColumn::make('profile.phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                Tables\Columns\TextColumn::make('profile.nric')
                    ->label('NRIC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                Tables\Columns\TextColumn::make('userAddress.city')
                    ->label('City')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Not set'),
                Tables\Columns\TextColumn::make('centres.name')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('profile_complete')
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
                Tables\Filters\SelectFilter::make('centres')
                    ->relationship('centres', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->visible(fn (User $record) => Auth::user()->can('view', $record)),
                    
                    Tables\Actions\EditAction::make()
                        ->visible(fn (User $record) => Auth::user()->can('update', $record)),
                    
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (User $record) => Auth::user()->can('delete', $record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
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
            RelationManagers\CentresRelationManager::class,
            RelationManagers\ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParents::route('/'),
            'create' => Pages\CreateParent::route('/create'),
            'edit' => Pages\EditParent::route('/{record}/edit'),
        ];
    }
}
