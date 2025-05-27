<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\InvoiceStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only allow viewing invoices if the user can view the owner record and has appropriate permissions
        return Auth::user()->can('view', $ownerRecord) && 
               Auth::user()->can('viewAny', \App\Models\Invoice::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('date')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('due_at')
                    ->date('M d, Y')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),
                    
                Tables\Filters\Filter::make('overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => Auth::user()->can('create', \App\Models\Invoice::class))
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        // Set tenant_id to the current tenant
                        $data['tenant_id'] = Auth::user()->current_tenant_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn ($record) => Auth::user()->can('view', $record)),
                
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => Auth::user()->can('update', $record)),
                
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => Auth::user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
