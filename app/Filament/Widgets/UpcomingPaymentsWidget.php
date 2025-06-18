<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Filament\Resources\InvoiceResource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingPaymentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Upcoming Payments';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1;
    
    public static function canView(): bool
    {
        return Auth::user()->can('viewUpcomingPayments', \App\Filament\Pages\FinanceDashboard::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('status', 'pending')
                    ->orderBy('due_at')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('due_at')
                    ->date()
                    ->sortable()
                    ->label('Due Date'),
                TextColumn::make('total')
                    ->money('MYR', 100)
                    ->sortable(),
                TextColumn::make('days_until_due')
                    ->state(function (Invoice $record): string {
                        $daysUntilDue = now()->diffInDays($record->due_at, false);
                        if ($daysUntilDue < 0) {
                            return 'Overdue ' . abs($daysUntilDue) . ' days';
                        }
                        return $daysUntilDue . ' days';
                    })
                    ->label('Due In')
                    ->color(function (Invoice $record): string {
                        $daysUntilDue = now()->diffInDays($record->due_at, false);
                        if ($daysUntilDue < 0) {
                            return 'danger'; // Overdue
                        } elseif ($daysUntilDue <= 3) {
                            return 'warning'; // Due soon
                        }
                        return 'success'; // Normal
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ])
            ->paginated(false);
    }
}