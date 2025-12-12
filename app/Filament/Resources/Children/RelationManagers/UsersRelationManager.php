<?php

namespace App\Filament\Resources\Children\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\User;
use Filament\Forms;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Filament\Schemas\Schema;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Select::make('relationship_type')
                    ->label('Relationship')
                    ->options([
                        'parent' => 'Parent',
                        'guardian' => 'Guardian',
                        'relative' => 'Relative',
                        'other' => 'Other',
                    ])
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pivot.relationship_type')
                    ->label('Relationship')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255),
                        Select::make('relationship_type')
                            ->label('Relationship')
                            ->options([
                                'parent' => 'Parent',
                                'guardian' => 'Guardian',
                                'relative' => 'Relative',
                                'other' => 'Other',
                            ])
                            ->required(),
                    ])
                    ->using(function (array $data, RelationManager $livewire): Model {
                        // Create the user
                        $user = User::create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                        ]);
                        
                        // Return the user
                        return $user;
                    })
                    ->callAfter(function (Model $record, array $data, RelationManager $livewire): void {
                        // Attach the record with pivot data
                        $livewire->ownerRecord->users()->attach($record, [
                            'relationship_type' => $data['relationship_type'],
                        ]);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Select::make('relationship_type')
                            ->label('Relationship')
                            ->options([
                                'parent' => 'Parent',
                                'guardian' => 'Guardian',
                                'relative' => 'Relative',
                                'other' => 'Other',
                            ])
                            ->required(),
                    ])
                    ->mutateRecordDataUsing(function (array $data, Model $record): array {
                        // Get the relationship type from pivot
                        $pivotData = $record->pivot;
                        $data['relationship_type'] = $pivotData->relationship_type;
                        return $data;
                    })
                    ->mutateFormDataBeforeSave(function (array $data, Model $record): array {
                        // Remove relationship_type from data before saving user
                        $relationshipType = $data['relationship_type'];
                        unset($data['relationship_type']);
                        
                        // Update the pivot table separately
                        $record->pivot->update([
                            'relationship_type' => $relationshipType,
                        ]);
                        
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
