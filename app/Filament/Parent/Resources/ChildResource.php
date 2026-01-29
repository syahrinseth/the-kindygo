<?php

namespace App\Filament\Parent\Resources;

use App\Enums\ChildStatus;
use App\Filament\Forms\ChildForm;
use App\Filament\Parent\Resources\ChildResource\Pages\CreateChild;
use App\Filament\Parent\Resources\ChildResource\Pages\EditChild;
use App\Filament\Parent\Resources\ChildResource\Pages\ListChildren;
use App\Filament\Parent\Resources\ChildResource\Pages\ViewChild;
use App\Filament\Parent\Resources\ChildResource\RelationManagers\CentresRelationManager;
use App\Models\Child;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChildResource extends Resource
{
    protected static ?string $model = Child::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'My Children';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // Parents can only see their own children
        if ($user) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(ChildForm::withoutAssociatedUsers());
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
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'pink',
                        default => 'gray',
                    }),
                TextColumn::make('centres.name')
                    ->label('Centres')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('tenant_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Child $record): string {
                        $user = Auth::user();
                        if (! $user || ! $user->current_tenant_id) {
                            return 'Unknown';
                        }

                        $status = $record->getStatusAtTenant($user->current_tenant_id);

                        return $status ? ucfirst($status->value) : 'Unknown';
                    })
                    ->colors([
                        'danger' => function (Child $record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);

                            return $status === ChildStatus::NEW;
                        },
                        'success' => function (Child $record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);

                            return $status === ChildStatus::ACTIVE;
                        },
                        'warning' => function (Child $record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);

                            return $status === ChildStatus::RETURN;
                        },
                        'gray' => function (Child $record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);

                            return $status === ChildStatus::ALUMNI;
                        },
                    ]),
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
                SelectFilter::make('status')
                    ->options(ChildStatus::options())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        $user = Auth::user();
                        if (! $user || ! $user->current_tenant_id) {
                            return $query;
                        }

                        return $query->whereHas('tenants', function (Builder $subQuery) use ($data, $user) {
                            $subQuery->where('tenant_id', $user->current_tenant_id)
                                ->where('status', $data['value']);
                        });
                    }),
                SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CentresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChildren::route('/'),
            'create' => CreateChild::route('/create'),
            'view' => ViewChild::route('/{record}'),
            'edit' => EditChild::route('/{record}/edit'),
        ];
    }
}
