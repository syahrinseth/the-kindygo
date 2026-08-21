<?php

namespace App\Filament\Parent\Widgets;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Upcoming Invoices';

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(
                Invoice::query()
                    ->where('user_id', $user?->id)
                    ->whereIn('status', [
                        InvoiceStatus::PENDING,
                        InvoiceStatus::OVERDUE,
                        InvoiceStatus::PARTIALLY_PAID,
                    ])
                    ->where('total_amount', '>', 0)
                    ->with(['centre'])
                    ->orderBy('due_at', 'asc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('centre.name')
                    ->label('Centre')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Issue Date')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('due_at')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_until_due')
                    ->label('Days Until Due')
                    ->state(function (Invoice $record): string {
                        if (! $record->due_at) {
                            return 'No due date';
                        }

                        $days = Carbon::now()->startOfDay()->diffInDays($record->due_at->startOfDay(), false);

                        if ($days < 0) {
                            return abs($days).' day(s) overdue';
                        } elseif ($days === 0) {
                            return 'Due today';
                        } else {
                            return $days.' day(s)';
                        }
                    })
                    ->badge()
                    ->color(function (Invoice $record): string {
                        if (! $record->due_at) {
                            return 'gray';
                        }

                        $days = Carbon::now()->startOfDay()->diffInDays($record->due_at->startOfDay(), false);

                        if ($days < 0) {
                            return 'danger';
                        } elseif ($days <= 7) {
                            return 'warning';
                        } else {
                            return 'success';
                        }
                    }),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->state(fn (Invoice $record): int => $record->getRemainingBalance())
                    ->money('MYR', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state): string => match ($state) {
                        InvoiceStatus::PENDING => 'warning',
                        InvoiceStatus::OVERDUE => 'danger',
                        InvoiceStatus::PARTIALLY_PAID => 'info',
                        InvoiceStatus::PAID => 'success',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Pay Now')
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->size('sm')
                    ->url(fn (Invoice $record): string => route('filament.parent.pages.make-payment', ['preselect' => $record->id])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No upcoming invoices')
            ->emptyStateDescription('All your invoices have been paid.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
