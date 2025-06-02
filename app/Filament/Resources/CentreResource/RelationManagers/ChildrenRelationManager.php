<?php

namespace App\Filament\Resources\CentreResource\RelationManagers;

use App\Enums\ChildStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('first_name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                Forms\Components\TextInput::make('last_name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'pink',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenant_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record): string {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return 'Unknown';
                        }
                        
                        $status = $record->getStatusAtTenant($user->current_tenant_id);
                        return $status ? ucfirst($status->value) : 'Unknown';
                    })
                    ->colors([
                        'danger' => function ($record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::NEW;
                        },
                        'success' => function ($record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::ACTIVE;
                        },
                        'warning' => function ($record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::RETURN;
                        },
                        'gray' => function ($record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::ALUMNI;
                        },
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added to Centre')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
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
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereHas('tenants', function (Builder $subQuery) {
                        $user = Auth::user();
                        $subQuery->where('tenant_id', $user->current_tenant_id);
                    }))
                    ->visible(fn () => Auth::user()->can('create', $this->getOwnerRecord())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.children.view', $record)),
                Tables\Actions\DetachAction::make()
                    ->visible(fn ($record) => Auth::user()->can('update', $this->getOwnerRecord())),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn () => Auth::user()->can('update', $this->getOwnerRecord())),
                ]),
            ]);
    }
}
