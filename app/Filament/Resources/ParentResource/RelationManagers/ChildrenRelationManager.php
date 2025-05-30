<?php

namespace App\Filament\Resources\ParentResource\RelationManagers;

use App\Enums\ChildStatus;
use App\Filament\Forms\ChildForm;
use App\Filament\Resources\ChildResource;
use App\Models\Child;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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

    public function form(Form $form): Form
    {
        // Use the ChildForm basic schema for quick operations like detach forms
        return $form->schema(ChildForm::basic());
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
                    ->date('M d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'male' => 'info',
                        'female' => 'pink',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('pivot.relationship_type')
                    ->label('Relationship')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->badge(),
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ]),
                Tables\Filters\SelectFilter::make('relationship_type')
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status at Current Tenant')
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
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form(fn (Form $form) => $form->schema(ChildForm::withoutAssociatedUsers()))
                    ->visible(fn (Child $record) => Auth::user()->can('view', $record)),
                    
                Tables\Actions\EditAction::make()
                    ->form(fn (Form $form) => $form->schema(ChildForm::withoutAssociatedUsers()))
                    ->visible(fn (Child $record) => Auth::user()->can('update', $record)),
                    
                Tables\Actions\DetachAction::make()
                    ->visible(fn (Child $record) => Auth::user()->can('manageParents', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn () => Auth::user()->can('deleteAny', Child::class)),
                ]),
            ]);
    }
}
