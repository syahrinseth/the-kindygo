<?php

namespace App\Policies;

use App\Models\InvoiceItem;
use App\Models\User;

class InvoiceItemsLedgerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only super_admin, admin, and Principal can access the ledger
        return $user->hasRole(['super-admin', 'admin', 'principal']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvoiceItem $invoiceItem): bool
    {
        // Super Admin and Admin can view any invoice item
        if ($user->hasRole(['super-admin', 'admin'])) {
            return true;
        }

        // Principal and Teacher can view items within their scope
        if ($user->hasRole(['principal', 'teacher'])) {
            // Check if the invoice item belongs to their tenant
            if ($invoiceItem->invoice && $invoiceItem->invoice->tenant_id === $user->current_tenant_id) {
                // Check if the invoice item is associated with their assigned centres
                $userCentreIds = $user->centres->pluck('id')->toArray();
                if (in_array($invoiceItem->invoice->centre_id, $userCentreIds)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Ledger is read-only, no creation allowed
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InvoiceItem $invoiceItem): bool
    {
        // Ledger is read-only, no updates allowed
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InvoiceItem $invoiceItem): bool
    {
        // Ledger is read-only, no deletion allowed
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InvoiceItem $invoiceItem): bool
    {
        // Not applicable for ledger
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InvoiceItem $invoiceItem): bool
    {
        // Ledger is read-only, no deletion allowed
        return false;
    }

    /**
     * Determine whether the user can export ledger data.
     */
    public function export(User $user): bool
    {
        // Allow export for authorized users
        return $user->hasRole(['super-admin', 'admin', 'principal']);
    }

    /**
     * Determine whether the user can view financial details.
     */
    public function viewFinancials(User $user): bool
    {
        // Financial details visible to authorized roles
        return $user->hasRole(['super-admin', 'admin', 'principal']);
    }
}
