<?php

namespace App\Filament\Parent\Widgets;

use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('user_id', auth()->id())
                    ->whereIn('status', ['pending', 'overdue'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice No.')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Issue Date')
                    ->date(),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Due Date')
                    ->date(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Amount')
                    ->money('MYR', 100),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'danger' => 'overdue',
                    ]),
            ])
            ->paginated(false);
    }
}
