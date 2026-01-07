<?php

namespace App\Filament\Parent\Widgets;

use App\Models\Child;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ChildrenOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Child::query()
                    ->whereHas('users', function (Builder $query) {
                        $query->where('user_id', auth()->id());
                    })
                    ->with(['tenants', 'enrolments'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('mykid_no')
                    ->label('MyKid No.')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label('Date of Birth')
                    ->date(),
                Tables\Columns\TextColumn::make('enrolments_count')
                    ->label('Active Enrolments')
                    ->counts('enrolments')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
