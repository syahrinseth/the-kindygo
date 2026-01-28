<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Only allow viewing invoices if the user can view the owner record and has appropriate permissions
        return Auth::user()->can('view', $ownerRecord) &&
               Auth::user()->can('viewAny', Invoice::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('centre.name')
                    ->label('Centre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('due_at')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::DRAFT => 'gray',
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::PARTIALLY_PAID => 'info',
                        InvoiceStatus::PAID => 'success',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::CANCELLED => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total')
                    ->money('USD')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->pluck('value', 'value')->toArray())
                    ->multiple(),

                Filter::make('overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => Auth::user()->can('create', Invoice::class))
                    ->mutateDataUsing(function (array $data, $livewire): array {
                        // Set tenant_id to the current tenant
                        $data['tenant_id'] = Auth::user()->current_tenant_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn ($record) => Auth::user()->can('view', $record)),

                EditAction::make()
                    ->visible(fn ($record) => Auth::user()->can('update', $record)),

                DeleteAction::make()
                    ->visible(fn ($record) => Auth::user()->can('delete', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
