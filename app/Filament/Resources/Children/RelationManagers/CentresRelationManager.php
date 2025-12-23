<?php

namespace App\Filament\Resources\Children\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Schema;

class CentresRelationManager extends RelationManager
{
    protected static string $relationship = 'centres';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->disabled(),
                TextInput::make('campus.name')
                    ->label('Campus')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('campus.name')
                    ->label('Campus')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Added to Centre')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereHas('users', function (Builder $subQuery) {
                        $user = Auth::user();
                        $subQuery->where('users.id', $user->id);
                    }))
                    ->visible(fn () => Auth::user()->can('manageCentres', $this->getOwnerRecord())),
            ])
            ->recordActions([
                DetachAction::make()
                    ->visible(fn () => Auth::user()->can('manageCentres', $this->getOwnerRecord())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->visible(fn () => Auth::user()->can('manageCentres', $this->getOwnerRecord())),
                ]),
            ]);
    }
}
