<?php

namespace App\Filament\Parent\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->where('user_id', auth()->id())
                    ->with(['invoices'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date(),
                Tables\Columns\TextColumn::make('invoices')
                    ->label('Invoice No(s).')
                    ->formatStateUsing(fn ($record) => $record->invoices->pluck('number')->join(', '))
                    ->searchable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Payment Method'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('MYR', divideBy: 100),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'completed',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ]),
            ])
            ->paginated(false);
    }
}
