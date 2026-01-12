<?php

namespace App\Filament\Parent\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string $routePath = 'dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            \App\Filament\Parent\Widgets\QuickPayInvoicesWidget::class,
            \App\Filament\Parent\Widgets\UpcomingInvoicesWidget::class,
            \App\Filament\Parent\Widgets\RecentPaymentsWidget::class,
            \App\Filament\Parent\Widgets\ChildrenOverviewWidget::class,
        ];
    }
}
