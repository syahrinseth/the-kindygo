<?php

namespace App\Filament\Parent\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = Auth::user();
        $tenantId = $user?->current_tenant_id;

        if (! $user || ! $tenantId) {
            return $this->getEmptyStats();
        }

        // Calculate outstanding balance
        $outstandingData = $this->getOutstandingData($user->id);

        // Calculate overdue invoices count
        $overdueCount = $this->getOverdueCount($user->id);

        // Calculate paid this month
        $paidThisMonth = $this->getPaidThisMonth($user->id);

        return [
            Stat::make('Total Outstanding', 'RM '.number_format($outstandingData['total'] / 100, 2))
                ->description($outstandingData['count'].' unpaid invoice(s)')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->color('primary')
                ->chart($outstandingData['trend']),

            Stat::make('Overdue Invoices', (string) $overdueCount)
                ->description($overdueCount > 0 ? 'Requires immediate attention' : 'All invoices are up to date')
                ->descriptionIcon($overdueCount > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                ->color($overdueCount > 0 ? 'danger' : 'success'),

            Stat::make('Paid This Month', 'RM '.number_format($paidThisMonth['total'] / 100, 2))
                ->description($paidThisMonth['count'].' payment(s) made')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->chart($paidThisMonth['trend']),
        ];
    }

    /**
     * Get outstanding invoice data for the user.
     *
     * @return array{total: int, count: int, trend: array<int>}
     */
    public function getOutstandingData(int $userId): array
    {
        $invoices = Invoice::where('user_id', $userId)
            ->whereIn('status', [
                InvoiceStatus::PENDING,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::OVERDUE,
            ])
            ->where('total', '>', 0)
            ->get();

        $total = $invoices->sum(fn (Invoice $invoice) => $invoice->getRemainingBalance());
        $count = $invoices->filter(fn (Invoice $invoice) => $invoice->getRemainingBalance() > 0)->count();

        // Generate trend data for the last 7 days (simplified for display)
        $trend = $this->generateOutstandingTrend($userId);

        return [
            'total' => $total,
            'count' => $count,
            'trend' => $trend,
        ];
    }

    /**
     * Get count of overdue invoices.
     */
    public function getOverdueCount(int $userId): int
    {
        return Invoice::where('user_id', $userId)
            ->where(function ($query) {
                $query->where('status', InvoiceStatus::OVERDUE)
                    ->orWhere(function ($query) {
                        $query->whereIn('status', [InvoiceStatus::PENDING, InvoiceStatus::PARTIALLY_PAID])
                            ->where('due_at', '<', now());
                    });
            })
            ->where('total', '>', 0)
            ->count();
    }

    /**
     * Get paid this month data.
     *
     * @return array{total: int, count: int, trend: array<int>}
     */
    public function getPaidThisMonth(int $userId): array
    {
        $payments = Payment::where('user_id', $userId)
            ->where('status', PaymentStatus::PAID)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->get();

        $total = $payments->sum('amount');
        $count = $payments->count();

        // Generate trend data for the last 7 days
        $trend = $this->generatePaymentTrend($userId);

        return [
            'total' => $total,
            'count' => $count,
            'trend' => $trend,
        ];
    }

    /**
     * Generate outstanding trend for the last 7 data points.
     *
     * @return array<int>
     */
    private function generateOutstandingTrend(int $userId): array
    {
        // For simplicity, return a static trend line
        // In production, you could calculate daily outstanding balances
        return [0, 0, 0, 0, 0, 0, 0];
    }

    /**
     * Generate payment trend for the last 7 days.
     *
     * @return array<int>
     */
    private function generatePaymentTrend(int $userId): array
    {
        $trend = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyTotal = Payment::where('user_id', $userId)
                ->where('status', PaymentStatus::PAID)
                ->whereDate('paid_at', $date)
                ->sum('amount');

            $trend[] = (int) ($dailyTotal / 100); // Convert to dollars for chart
        }

        return $trend;
    }

    /**
     * Get empty stats when user is not authenticated.
     *
     * @return array<Stat>
     */
    private function getEmptyStats(): array
    {
        return [
            Stat::make('Total Outstanding', 'RM 0.00')
                ->description('No data available')
                ->color('gray'),

            Stat::make('Overdue Invoices', '0')
                ->description('No data available')
                ->color('gray'),

            Stat::make('Paid This Month', 'RM 0.00')
                ->description('No data available')
                ->color('gray'),
        ];
    }
}
