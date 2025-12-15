<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InvoiceChart;
use App\Filament\Widgets\InvoiceStats;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 0;

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check();
    }

    public function getWidgets(): array
    {
        $widgets = [];

        // if (Auth::user()->can('viewFinancialStats')) {
        $widgets[] = InvoiceStats::class;
        // }

        // if (Auth::user()->can('viewInvoiceAnalytics')) {
        $widgets[] = InvoiceChart::class;
        // }

        return $widgets;
    }
}
