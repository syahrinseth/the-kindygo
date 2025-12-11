<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InvoiceChart;
use App\Filament\Widgets\InvoiceListWidget;
use App\Filament\Widgets\InvoiceStats;
use App\Filament\Widgets\UpcomingPaymentsWidget;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class FinanceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static string $view = 'filament.pages.finance-dashboard';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Finance Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'finance-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        // Use the dedicated policy instead of checking invoice access
        return Auth::user()->can('viewFinanceDashboard', static::class);
    }

    public static function canAccess(): bool
    {
        // Add an additional layer of protection at the page level
        return Auth::user()->can('viewFinanceDashboard', static::class);
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return 1;
    }

    protected function getWidgets(): array
    {
        $widgets = [];

        // Only include widgets the user has permission to view
        if (Auth::user()->can('viewFinancialStats', static::class)) {
            // $widgets[] = InvoiceStats::class;
        }

        if (Auth::user()->can('viewInvoiceAnalytics', static::class)) {
            $widgets[] = InvoiceChart::class;
        }

        if (Auth::user()->can('viewUpcomingPayments', static::class)) {
            // $widgets[] = UpcomingPaymentsWidget::class;
        }

        if (Auth::user()->can('viewInvoiceList', static::class)) {
            // $widgets[] = InvoiceListWidget::class;
        }

        return $widgets;
    }

    public function getWidgetsColumns(): int | string | array
    {
        return 1;
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getFooterWidgetsColumns(): int | string | array
    {
        return 1;
    }
}
