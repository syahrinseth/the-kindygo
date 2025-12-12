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
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected string $view = 'filament.pages.finance-dashboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Finance Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'finance-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        // Use the dedicated policy instead of checking invoice access
        return Auth::user()->can('viewFinanceDashboard');
    }

    public static function canAccess(): bool
    {
        // Add an additional layer of protection at the page level
        return Auth::user()->can('viewFinanceDashboard');
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getWidgets(): array
    {
        $widgets = [];

        // Only include widgets the user has permission to view
        if (Auth::user()->can('viewFinancialStats')) {
            // $widgets[] = InvoiceStats::class;
        }

        if (Auth::user()->can('viewInvoiceAnalytics')) {
            $widgets[] = InvoiceChart::class;
        }

        if (Auth::user()->can('viewUpcomingPayments')) {
            // $widgets[] = UpcomingPaymentsWidget::class;
        }

        if (Auth::user()->can('viewInvoiceList')) {
            // $widgets[] = InvoiceListWidget::class;
        }

        return $widgets;
    }

    public function getWidgetsColumns(): int|string|array
    {
        return 1;
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
