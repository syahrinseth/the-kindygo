<?php

namespace App\Policies;

use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoiceItemsLedgerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Only super_admin, admin, and principle can access the ledger
        return in_array($user->role, ['Super Admin', 'Admin', 'Principle']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InvoiceItem $invoiceItem): bool
    {
        // Super admin can view any invoice item
        if ($user->role === 'Super Admin') {
            return true;
        }

        // Admin and principle can only view items associated with their invoices
        if (in_array($user->role, ['Admin', 'Principle'])) {
            return $invoiceItem->invoice && $invoiceItem->invoice->user_id === $user->id;
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
        return in_array($user->role, ['Super Admin', 'Admin', 'Principle']);
    }

    /**
     * Determine whether the user can view financial details.
     */
    public function viewFinancials(User $user): bool
    {
        // Financial details visible to authorized roles
        return in_array($user->role, ['Super Admin', 'Admin', 'Principle']);
    }
}
