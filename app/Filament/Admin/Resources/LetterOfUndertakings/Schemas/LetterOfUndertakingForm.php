<?php

namespace App\Filament\Admin\Resources\LetterOfUndertakings\Schemas;

use App\Models\User;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LetterOfUndertakingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Letter Content')
                    ->description('Enter the title and content of the letter of undertaking.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter letter title')
                            ->columnSpanFull(),
                        RichEditor::make('content')
                            ->label('Content')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                                'h2',
                                'h3',
                            ])
                            ->placeholder('Enter the letter content...')
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(2),

                Section::make('Settings')
                    ->description('Metadata and activation settings.')
                    ->schema([
                        TextInput::make('version')
                            ->label('Version Number')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->hint('Auto-generated based on tenant')
                            ->default(fn ($record) => $record?->version ?? 'Auto-generated')
                            ->helperText('Version number is automatically assigned when created.'),
                        Select::make('created_by')
                            ->label('Created By')
                            ->relationship('creator', 'name')
                            ->options(User::whereHas('tenants', fn ($query) => $query->where('tenants.id', Auth::user()->currentTenant()?->id ?? 0))->pluck('name', 'id'))
                            ->dehydrated(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Activating this letter will deactivate all other letters for this tenant and notify all parents.')
                            ->default(false)
                            ->inline(false),
                    ])
                    ->columnSpan(1),
            ]);
    }
}
