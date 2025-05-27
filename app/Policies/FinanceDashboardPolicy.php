<?php

namespace App\Policies;

use App\Models\User;

class FinanceDashboardPolicy
{
    /**
     * Determine whether the user can access the Finance Dashboard.
     */
    public function viewFinanceDashboard(User $user): bool
    {
        // Super Admin, Admin, and Principal can access the Finance Dashboard
        // Teachers and Parents cannot access finance information
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view financial statistics.
     */
    public function viewFinancialStats(User $user): bool
    {
        // Same as dashboard access - only financial managers
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view invoice charts and analytics.
     */
    public function viewInvoiceAnalytics(User $user): bool
    {
        // Super Admin and Admin can view all analytics
        // Principal can view analytics for their centres only
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view upcoming payments.
     */
    public function viewUpcomingPayments(User $user): bool
    {
        // Same roles that can access invoices can view upcoming payments
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view the invoice list widget.
     */
    public function viewInvoiceList(User $user): bool
    {
        // Delegate to the existing Invoice policy
        return $user->can('viewAny', \App\Models\Invoice::class);
    }

    /**
     * Determine whether the user can export financial data.
     */
    public function exportFinancialData(User $user): bool
    {
        // Only Super Admin and Admin can export financial data
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can view all financial data or is restricted by centre.
     */
    public function viewAllFinancialData(User $user): bool
    {
        // Super Admin and Admin can view all financial data in their tenant
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }
}
