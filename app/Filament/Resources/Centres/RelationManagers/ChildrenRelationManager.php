<?php

namespace App\Filament\Resources\Centres\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\AttachAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DetachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use App\Enums\ChildStatus;
use App\Filament\Forms\ChildForm;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Schema;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(ChildForm::make());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                SpatieMediaLibraryImageColumn::make('photo')
                    ->collection('photo')
                    ->conversion('thumb')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.svg'))
                    ->size(40),
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
                TextColumn::make('tenant_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record): string {
                        $user = Auth::user();
                        if (! $user || ! $user->current_tenant_id) {
                            return 'Unknown';
                        }

                        $status = $record->getStatusAtTenant($user->current_tenant_id);
                        return $status ? ucfirst($status->value) : 'Unknown';
                    })
                    ->colors([
                        'danger' => function ($record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::NEW;
                        },
                        'success' => function ($record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::ACTIVE;
                        },
                        'warning' => function ($record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::RETURN;
                        },
                        'gray' => function ($record): bool {
                            $user = Auth::user();
                            if (! $user || ! $user->current_tenant_id) {
                                return false;
                            }

                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::ALUMNI;
                        },
                    ]),
                TextColumn::make('created_at')
                    ->label('Added to Centre')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ChildStatus::options())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return $query;
                        }
                        
                        return $query->whereHas('tenants', function (Builder $subQuery) use ($data, $user) {
                            $subQuery->where('tenant_id', $user->current_tenant_id)
                                     ->where('status', $data['value']);
                        });
                    }),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereHas('tenants', function (Builder $subQuery) {
                        $user = Auth::user();
                        $subQuery->where('tenant_id', $user->current_tenant_id);
                    }))
                    ->visible(fn () => Auth::user()->can('create', $this->getOwnerRecord())),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.children.view', $record)),
                DetachAction::make()
                    ->visible(fn ($record) => Auth::user()->can('update', $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->visible(fn () => Auth::user()->can('update', $this->getOwnerRecord())),
                ]),
            ]);
    }
}
