<?php

namespace App\Filament\Parent\Widgets;

use App\Models\Child;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChildrenOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'My Children';

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(
                Child::query()
                    ->whereHas('users', function (Builder $query) use ($user) {
                        $query->where('user_id', $user?->id);
                    })
                    ->with(['tenants', 'enrolments.centre'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('mykid_no')
                    ->label('MyKid No.')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('date_of_birth')
                    ->label('Date of Birth')
                    ->date('d M Y')
                    ->description(fn (Child $record): string => $record->date_of_birth ? $record->date_of_birth->age.' years old' : ''),
                Tables\Columns\TextColumn::make('enrolment_centre_names')
                    ->label('Enrolled At')
                    ->badge()
                    ->color('primary')
                    ->separator(', '),
                Tables\Columns\TextColumn::make('enrolments_count')
                    ->label('Active Enrolments')
                    ->counts('enrolments')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->size('sm')
                    ->url(fn (Child $record): string => route('filament.parent.resources.children.view', ['record' => $record])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No children registered')
            ->emptyStateDescription('Children will appear here once they are added to your account.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
