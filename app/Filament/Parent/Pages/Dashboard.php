<?php

namespace App\Filament\Parent\Pages;

use App\Filament\Parent\Widgets\ChildrenOverviewWidget;
use App\Filament\Parent\Widgets\QuickPayInvoicesWidget;
use App\Filament\Parent\Widgets\RecentPaymentsWidget;
use App\Filament\Parent\Widgets\StatsOverviewWidget;
use App\Filament\Parent\Widgets\UpcomingInvoicesWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            // Row 1: Stats Overview (full width)
            StatsOverviewWidget::class,

            // Row 2: Finance Widgets (2 columns on desktop)
            QuickPayInvoicesWidget::class,
            RecentPaymentsWidget::class,

            // Row 3: Upcoming Invoices (full width)
            UpcomingInvoicesWidget::class,

            // Row 4: Children Overview (full width)
            ChildrenOverviewWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 2,
            'xl' => 2,
        ];
    }
}
