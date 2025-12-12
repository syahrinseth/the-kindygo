<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        // Super Admin, Admin, Principal can view the list of invoices
        // Parents can also view the list of invoices related to them
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal', 'Parent']);
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Check role-based permissions first
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            // Super Admin and Admin can view any invoice in their tenant
            return $invoice->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only view invoices for their centres
        if ($user->hasRole('Principal')) {
            return $invoice->tenant_id === $user->current_tenant_id && 
                   $user->centres()->where('centres.id', $invoice->centre_id)->exists();
        }
        
        // Parents can only view invoices that are directly related to them
        if ($user->hasRole('Parent')) {
            // Direct invoices where user_id matches
            if ($invoice->user_id === $user->id) {
                return true;
            }
            
            // Check if any of the user's children have invoices related to their centre
            // This is a simplified check - you might need to adjust based on your exact data model
            return $invoice->tenant_id === $user->current_tenant_id && 
                   $user->children()
                        ->whereHas('tenants', function ($query) use ($invoice) {
                            $query->where('tenant_id', $invoice->tenant_id);
                        })->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        // Only Super Admin and Admin can create invoices
        return $user->hasAnyRole(['Super Admin', 'Admin']);
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        // Super Admin and Admin can update any invoice in their tenant
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $invoice->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only update draft invoices for their centres
        if ($user->hasRole('Principal')) {
            return $invoice->tenant_id === $user->current_tenant_id && 
                   $user->centres()->where('centres.id', $invoice->centre_id)->exists() &&
                   $invoice->status === InvoiceStatus::DRAFT;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only draft invoices can be deleted
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }
        
        // Super Admin and Admin can delete any draft invoice in their tenant
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $invoice->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only delete draft invoices for their centres
        if ($user->hasRole('Principal')) {
            return $invoice->tenant_id === $user->current_tenant_id && 
                   $user->centres()->where('centres.id', $invoice->centre_id)->exists();
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete any invoices.
     */
    public function deleteAny(User $user): bool
    {
        // Only Super Admin and Admin can bulk delete invoices
        return $user->hasAnyRole(['Super Admin', 'Admin']) && 
               $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can permanently delete the invoice.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        // Only Super Admin can permanently delete invoices
        return $user->hasRole('Super Admin') && 
               $invoice->tenant_id === $user->current_tenant_id;
    }
}
