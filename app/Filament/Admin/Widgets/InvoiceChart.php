<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\InvoiceStatus;
use App\Filament\Admin\Pages\FinanceDashboard;
use App\Models\Invoice;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class InvoiceChart extends ChartWidget
{
    protected ?string $heading = 'Monthly Invoice Totals';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return Auth::user()->can('viewInvoiceAnalytics', FinanceDashboard::class);
    }

    protected function getData(): array
    {
        $user = Auth::user();

        // If no tenant is selected, show empty chart
        if (! $user->current_tenant_id) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get the last 6 months
        $months = collect();
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $months->push($month);
            $labels[] = $month->format('M Y');
        }

        // Create a base query filtered by tenant
        $baseQuery = Invoice::where('tenant_id', $user->current_tenant_id);

        // If user is Principal, only show their centres
        if ($user->hasRole('Principal')) {
            $baseQuery->whereIn('centre_id', $user->centres()->pluck('centres.id'));
        }

        // Get monthly totals for different statuses
        $pendingData = [];
        $paidData = [];
        $overdueData = [];

        foreach ($months as $month) {
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();

            // Pending invoices for this month
            $pendingData[] = (clone $baseQuery)
                ->where('status', InvoiceStatus::PENDING)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('total') / 100; // Convert to dollars

            // Paid invoices for this month
            $paidData[] = (clone $baseQuery)
                ->where('status', InvoiceStatus::PAID)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('total') / 100; // Convert to dollars

            // Overdue invoices for this month
            $overdueData[] = (clone $baseQuery)
                ->where('status', InvoiceStatus::OVERDUE)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->sum('total') / 100; // Convert to dollars
        }

        return [
            'datasets' => [
                [
                    'label' => 'Paid',
                    'data' => $paidData,
                    'backgroundColor' => 'rgba(40, 167, 69, 0.2)',
                    'borderColor' => 'rgba(40, 167, 69, 1)',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Pending',
                    'data' => $pendingData,
                    'backgroundColor' => 'rgba(255, 193, 7, 0.2)',
                    'borderColor' => 'rgba(255, 193, 7, 1)',
                    'borderWidth' => 2,
                ],
                [
                    'label' => 'Overdue',
                    'data' => $overdueData,
                    'backgroundColor' => 'rgba(220, 53, 69, 0.2)',
                    'borderColor' => 'rgba(220, 53, 69, 1)',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) {
                            return context.dataset.label + ": RM " + context.parsed.y.toFixed(2);
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "RM " + value.toFixed(2);
                        }',
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
