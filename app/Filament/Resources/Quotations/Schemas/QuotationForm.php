<?php

namespace App\Filament\Resources\Quotations\Schemas;

use App\Enums\QuotationStatus;
use App\Models\Centre;
use App\Models\Child;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class QuotationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->schema([
                        Section::make('Quotation Information')
                            ->description('Select the centre, parent, and child for this quotation.')
                            ->schema([
                                Select::make('centre_id')
                                    ->label('Centre')
                                    ->relationship('centre', 'name')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (!$user->current_tenant_id) {
                                            return [];
                                        }

                                        $query = Centre::where('tenant_id', $user->current_tenant_id);

                                        if ($user->hasRole('Principal')) {
                                            $query->whereHas('users', function (Builder $q) use ($user) {
                                                $q->where('users.id', $user->id);
                                            });
                                        }

                                        return $query->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpanFull(),

                                Select::make('user_id')
                                    ->label('Parent')
                                    ->relationship('user', 'name')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (!$user->current_tenant_id) {
                                            return [];
                                        }

                                        return User::whereHas('tenants', function (Builder $query) use ($user) {
                                            $query->where('tenants.id', $user->current_tenant_id);
                                        })->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpanFull(),

                                Select::make('child_id')
                                    ->label('Child')
                                    ->relationship('child', 'full_name')
                                    ->options(function () {
                                        $user = Auth::user();
                                        if (!$user->current_tenant_id) {
                                            return [];
                                        }

                                        return Child::whereHas('tenants', function (Builder $query) use ($user) {
                                            $query->where('tenants.id', $user->current_tenant_id);
                                        })->get()->pluck('full_name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make('Date & Validity')
                            ->description('Set quotation date and expiry.')
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Quotation Date')
                                    ->required()
                                    ->native(false)
                                    ->default(now()),

                                DatePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->required()
                                    ->native(false)
                                    ->default(now()->addDays(30))
                                    ->helperText('Default validity is 30 days.'),
                            ])
                            ->columns(2),

                        Section::make('Terms & Conditions')
                            ->description('Add terms and notes for this quotation.')
                            ->schema([
                                Textarea::make('terms_conditions')
                                    ->label('Terms & Conditions')
                                    ->rows(5)
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->collapsed(),
                    ])
                    ->columnSpan(2),

                Section::make('Status')
                    ->description('Current quotation status.')
                    ->schema([
                        Select::make('status')
                            ->label('Quotation Status')
                            ->options(QuotationStatus::options())
                            ->required()
                            ->native(false)
                            ->default(QuotationStatus::DRAFT->value)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),

                Hidden::make('tenant_id')
                    ->default(function () {
                        return Auth::user()->current_tenant_id;
                    }),
            ]);
    }
}
