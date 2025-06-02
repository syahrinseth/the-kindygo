<?php

namespace App\Filament\Resources;

use App\Enums\ChildStatus;
use App\Filament\Forms\ChildForm;
use App\Filament\Resources\ChildResource\Pages;
use App\Filament\Resources\ChildResource\RelationManagers;
use App\Models\Centre;
use App\Models\Child;
use App\Models\Scopes\ChildStatusScope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ChildResource extends Resource
{
    protected static ?string $model = Child::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    
    protected static ?string $navigationLabel = 'Children';
    
    protected static ?int $navigationSort = 2;

    protected static ?string $tenantOwnershipRelationshipName = 'tenants';
    
    public static function canViewAny(): bool
    {
        return Auth::user()->can('viewAny', Child::class);
    }
    
    public static function canView(Model $record): bool
    {
        return Auth::user()->can('view', $record);
    }
    
    public static function canCreate(): bool
    {
        return Auth::user()->can('create', Child::class);
    }
    
    public static function canEdit(Model $record): bool
    {
        return Auth::user()->can('update', $record);
    }
    
    public static function canDelete(Model $record): bool
    {
        return Auth::user()->can('delete', $record);
    }
    
    public static function canDeleteAny(): bool
    {
        return Auth::user()->can('deleteAny', Child::class);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        // If user is Parent, restrict to their children
        if ($user->hasRole('Parent')) {
            $query->whereHas('users', fn($q) => $q->where('users.id', $user->id));
        }
        
        // If user is Teacher or Principal, restrict to children in their centres
        if ($user->hasAnyRole(['Teacher', 'Principal'])) {
            $userCentreIds = $user->centres()
                ->where('centres.tenant_id', $user->current_tenant_id)
                ->pluck('centres.id');
                
            if ($userCentreIds->isNotEmpty()) {
                $query->whereHas('centres', fn($q) => $q->whereIn('centres.id', $userCentreIds));
            } else {
                // If user has no centres assigned, show no children
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema(ChildForm::make());
    }

    public static function table(Table $table): Table
    {
        return $table
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
                Tables\Columns\TextColumn::make('centres.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (Child $record): string {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return 'Unknown';
                        }
                        
                        $status = $record->getStatusAtTenant($user->current_tenant_id);
                        return $status ? ucfirst($status->value) : 'Unknown';
                    })
                    ->colors([
                        'danger' => function (Child $record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::NEW;
                        },
                        'success' => function (Child $record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::ACTIVE;
                        },
                        'warning' => function (Child $record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::RETURN;
                        },
                        'gray' => function (Child $record): bool {
                            $user = Auth::user();
                            if (!$user || !$user->current_tenant_id) {
                                return false;
                            }
                            
                            $status = $record->getStatusAtTenant($user->current_tenant_id);
                            return $status === ChildStatus::ALUMNI;
                        },
                    ]),
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
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
                Tables\Filters\SelectFilter::make('centre')
                    ->label('Centre')
                    ->options(function () {
                        $user = Auth::user();
                        
                        if (!$user || !$user->current_tenant_id) {
                            return [];
                        }
                        
                        // Get centres based on user role and permissions
                        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
                            // Super Admin and Admin can see all centres in their tenant
                            return Centre::where('tenant_id', $user->current_tenant_id)
                                ->pluck('name', 'id');
                        } else if ($user->hasAnyRole(['Teacher', 'Principal'])) {
                            // Teachers and Principals can only see centres they're assigned to
                            return $user->centres()
                                ->where('centres.tenant_id', $user->current_tenant_id)
                                ->pluck('name', 'id');
                        }
                        
                        return [];
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        
                        return $query->whereHas('centres', function (Builder $subQuery) use ($data) {
                            $subQuery->where('centres.id', $data['value']);
                        });
                    }),
                Tables\Filters\Filter::make('date_of_birth')
                    ->form([
                        Forms\Components\DatePicker::make('birth_from'),
                        Forms\Components\DatePicker::make('birth_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['birth_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date_of_birth', '>=', $date),
                            )
                            ->when(
                                $data['birth_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date_of_birth', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(function (Child $record): bool {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return false;
                        }
                        
                        $status = $record->getStatusAtTenant($user->current_tenant_id);
                        return ($status === ChildStatus::NEW || $status === ChildStatus::RETURN) && 
                               $user->can('changeStatus', $record);
                    })
                    ->action(function (Child $record): void {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return;
                        }
                        
                        $record->activateAtTenant($user->current_tenant_id);
                    }),
                Tables\Actions\Action::make('return')
                    ->label('Mark as Returning')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function (Child $record): bool {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return false;
                        }
                        
                        $status = $record->getStatusAtTenant($user->current_tenant_id);
                        return $status === ChildStatus::ALUMNI && 
                               $user->can('changeStatus', $record);
                    })
                    ->action(function (Child $record): void {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return;
                        }
                        
                        $record->markAsReturningAtTenant($user->current_tenant_id);
                    }),
                Tables\Actions\Action::make('alumni')
                    ->label('Mark as Alumni')
                    ->icon('heroicon-o-academic-cap')
                    ->color('gray')
                    ->visible(function (Child $record): bool {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return false;
                        }
                        
                        $status = $record->getStatusAtTenant($user->current_tenant_id);
                        return $status === ChildStatus::ACTIVE && 
                               $user->can('changeStatus', $record);
                    })
                    ->action(function (Child $record): void {
                        $user = Auth::user();
                        if (!$user || !$user->current_tenant_id) {
                            return;
                        }
                        
                        $record->markAsAlumniAtTenant($user->current_tenant_id);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CentresRelationManager::class,
            // RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChildren::route('/'),
            'create' => Pages\CreateChild::route('/create'),
            'view' => Pages\ViewChild::route('/{record}'),
            'edit' => Pages\EditChild::route('/{record}/edit'),
        ];
    }
}
