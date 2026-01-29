<?php

namespace App\Filament\Parent\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentPaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    // Single column on mobile, takes 1 column on md+ (half width in 2-col grid)
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 1,
        'lg' => 1,
        'xl' => 1,
    ];

    protected static ?string $heading = 'Recent Payments';

    public function table(Table $table): Table
    {
        $user = Auth::user();

        return $table
            ->query(
                Payment::query()
                    ->where('user_id', $user?->id)
                    ->with(['invoices'])
                    ->latest('created_at')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_no')
                    ->label('Reference')
                    ->searchable()
                    ->limit(12),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('MYR', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PaymentStatus $state): string => match ($state) {
                        PaymentStatus::PAID => 'success',
                        PaymentStatus::PENDING => 'warning',
                        PaymentStatus::FAILED => 'danger',
                        PaymentStatus::CANCELLED => 'gray',
                        PaymentStatus::REFUNDED => 'info',
                        default => 'gray',
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Your payment history will appear here.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
