<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Enums\InvoiceStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class InvoiceStats extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return Auth::user()->can('viewFinancialStats', \App\Filament\Pages\FinanceDashboard::class);
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        
        // If no tenant is selected, show no stats
        if (!$user->current_tenant_id) {
            return [];
        }
        
        // Create a base query filtered by tenant
        $query = Invoice::where('tenant_id', $user->current_tenant_id);
        
        // If user is Principal, only show their centres
        if ($user->hasRole('Principal')) {
            $query->whereIn('centre_id', $user->centres()->pluck('centres.id'));
        }
        
        // Get stats for different invoice statuses
        $draftCount = (clone $query)->where('status', InvoiceStatus::DRAFT)->count();
        $pendingCount = (clone $query)->where('status', InvoiceStatus::PENDING)->count();
        $paidCount = (clone $query)->where('status', InvoiceStatus::PAID)->count();
        $overdueCount = (clone $query)->where('status', InvoiceStatus::OVERDUE)->count();
        
        // Get total amounts
        $totalPending = (clone $query)
            ->where('status', InvoiceStatus::PENDING)
            ->sum('total');
        
        $totalOverdue = (clone $query)
            ->where('status', InvoiceStatus::OVERDUE)
            ->sum('total');
        
        $totalPaid = (clone $query)
            ->where('status', InvoiceStatus::PAID)
            ->sum('total');
        
        // Format amounts for display
        $formatMoney = fn ($amount) => 'RM ' . number_format($amount / 100, 2);
        
        return [
            Stat::make('Pending Invoices', $pendingCount)
                ->description('Total: ' . $formatMoney($totalPending))
                ->color('warning')
                ->chart([2, 3, 3, 4, 3, 2, 1, $pendingCount]),
                
            Stat::make('Overdue Invoices', $overdueCount)
                ->description('Total: ' . $formatMoney($totalOverdue))
                ->color('danger')
                ->chart([1, 2, 2, 1, 2, 3, 3, $overdueCount]),
                
            Stat::make('Paid Invoices', $paidCount)
                ->description('Total: ' . $formatMoney($totalPaid))
                ->color('success')
                ->chart([4, 5, 7, 8, 9, 10, 11, $paidCount]),
        ];
    }
}
