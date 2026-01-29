<?php

namespace App\Filament\Admin\Resources\Parents\RelationManagers;

use App\Enums\ChildStatus;
use App\Filament\Forms\ChildForm;
use App\Models\Child;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only allow viewing children if the user can view the owner record and has appropriate permissions
        return Auth::user()->can('view', $ownerRecord) &&
               Auth::user()->can('viewAny', Child::class);
    }

    public function form(Schema $schema): Schema
    {
        // Use the full ChildForm without associated users since we're in parent context
        return $schema
            ->components(ChildForm::withoutAssociatedUsers());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Photo')
                    ->collection('photo')
                    ->conversion('thumb')
                    ->height(40)
                    ->width(40)
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png'))
                    ->toggleable(),
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_of_birth')
                    ->date('M d, Y')
                    ->sortable(),
                TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'pink',
                        default => 'gray',
                    }),
                TextColumn::make('pivot.relationship_type')
                    ->label('Relationship')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge(),
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
            ])
            ->filters([
                SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
                SelectFilter::make('relationship_type')
                    ->label('Relationship')
                    ->options([
                        'parent' => 'Parent',
                        'guardian' => 'Guardian',
                        'relative' => 'Relative',
                        'other' => 'Other',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->wherePivot('relationship_type', $data['value']);
                    }),
                SelectFilter::make('status')
                    ->label('Status at Current Tenant')
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
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(fn (Form $form) => $form->schema(ChildForm::withoutAssociatedUsers()))
                    ->visible(fn (Child $record) => Auth::user()->can('view', $record)),

                EditAction::make()
                    ->schema(fn (Form $form) => $form->schema(ChildForm::withoutAssociatedUsers()))
                    ->visible(fn (Child $record) => Auth::user()->can('update', $record)),

                DetachAction::make()
                    ->visible(fn (Child $record) => Auth::user()->can('manageParents', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Child::class)),
                ]),
            ]);
    }
}
