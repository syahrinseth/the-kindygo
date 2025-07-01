<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EInvoicePolicy
{
    /**
     * Determine whether the user can view any e-invoices.
     */
    public function viewAny(User $user): bool
    {
        // Only Super Admin, Admin, and Principal can view e-invoice list
        // Parents typically don't need to manage e-invoice compliance
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view the e-invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Check role-based permissions first
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            // Super Admin and Admin can view any e-invoice in their tenant
            return $invoice->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only view e-invoices for their centres
        if ($user->hasRole('Principal')) {
            return $invoice->tenant_id === $user->current_tenant_id && 
                   $user->centres()->where('centres.id', $invoice->centre_id)->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can create e-invoices.
     * E-invoices are typically generated from existing invoices, not created directly.
     */
    public function create(User $user): bool
    {
        // Only Super Admin and Admin can manage e-invoice creation
        return $user->hasAnyRole(['Super Admin', 'Admin']) && 
               $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can update the e-invoice.
     * E-invoices have limited editability due to compliance requirements.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Only Super Admin and Admin can update e-invoice information
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $invoice->tenant_id === $user->current_tenant_id;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the e-invoice.
     * E-invoices typically cannot be deleted due to compliance requirements.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // E-invoices that have been submitted to government cannot be deleted
        if (!empty($invoice->einvoice_uuid) || !empty($invoice->einvoice_submission_id)) {
            return false;
        }
        
        // Only Super Admin can delete e-invoices that haven't been submitted
        return $user->hasRole('Super Admin') && 
               $invoice->tenant_id === $user->current_tenant_id;
    }

    /**
     * Determine whether the user can delete any e-invoices.
     */
    public function deleteAny(User $user): bool
    {
        // Very restricted - only Super Admin can bulk delete e-invoices
        return $user->hasRole('Super Admin') && 
               $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can permanently delete the e-invoice.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        // E-invoices should never be permanently deleted due to audit requirements
        return false;
    }

    /**
     * Determine whether the user can submit the invoice to e-invoice system.
     */
    public function submit(User $user, Invoice $invoice): bool
    {
        // Only Super Admin and Admin can submit e-invoices
        if (!$user->hasAnyRole(['Super Admin', 'Admin'])) {
            return false;
        }
        
        // Must be in the same tenant
        if ($invoice->tenant_id !== $user->current_tenant_id) {
            return false;
        }
        
        // Can only submit if not already submitted
        return empty($invoice->einvoice_uuid) && empty($invoice->einvoice_submission_id);
    }

    /**
     * Determine whether the user can cancel the e-invoice submission.
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        // Only Super Admin and Admin can cancel e-invoices
        if (!$user->hasAnyRole(['Super Admin', 'Admin'])) {
            return false;
        }
        
        // Must be in the same tenant
        if ($invoice->tenant_id !== $user->current_tenant_id) {
            return false;
        }
        
        // Can only cancel if it has been submitted but not finalized
        return !empty($invoice->einvoice_uuid) && 
               $invoice->einvoice_status !== 'cancelled' &&
               $invoice->einvoice_status !== 'validated';
    }

    /**
     * Determine whether the user can view the e-invoice validation details.
     */
    public function viewValidation(User $user, Invoice $invoice): bool
    {
        // Same as view permission
        return $this->view($user, $invoice);
    }

    /**
     * Determine whether the user can bulk submit e-invoices.
     */
    public function bulkSubmit(User $user): bool
    {
        // Only Super Admin and Admin can bulk submit e-invoices
        return $user->hasAnyRole(['Super Admin', 'Admin']) && 
               $user->current_tenant_id !== null;
    }
}
