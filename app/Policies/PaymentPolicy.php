<?php

namespace App\Policies;

use App\Enums\Gateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine whether the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        // Super Admin, Admin, Principal can view the list of payments
        // Parents can also view the list of payments related to them
        return $user->hasAnyRole(['super-admin', 'admin', 'principal', 'parent']);
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, Payment $payment): bool
    {
        // Check role-based permissions first
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            // Super Admin and Admin can view any payment in their tenant
            return $payment->tenant_id === $user->current_tenant_id;
        }

        // Principal can only view payments for their centres
        if ($user->hasRole('principal')) {
            return $payment->tenant_id === $user->current_tenant_id &&
                   ($payment->centres->isEmpty() ||
                    $payment->centres->intersect($user->centres)->isNotEmpty());
        }

        // Parents can only view payments that are directly related to them
        if ($user->hasRole('parent')) {
            // Direct payments where user_id matches
            if ($payment->user_id === $user->id) {
                return true;
            }

            // Check if any of the user's children have payments related to their centre
            return $payment->tenant_id === $user->current_tenant_id &&
                   $user->children()
                       ->whereHas('tenants', function ($query) use ($payment) {
                           $query->where('tenant_id', $payment->tenant_id);
                       })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can make a payment.
     */
    public function makePayment(User $user): bool
    {
        // Super Admin, Admin, Principal, Parent, and Teacher can make payments
        return $user->hasAnyRole(['super-admin', 'admin', 'principal', 'parent', 'teacher']);
    }

    /**
     * Determine whether the user can create payments.
     */
    public function create(User $user): bool
    {
        // Super Admin, Admin, Principal, Parent, and Teacher can create payments
        // But additional checks are done in the MakePaymentAction for specific invoices
        return $user->hasAnyRole(['super-admin', 'admin', 'principal', 'parent', 'teacher']) &&
               $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can update the payment.
     */
    public function update(User $user, Payment $payment): bool
    {
        // Super Admin and Admin can update any payment in their tenant
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $payment->tenant_id === $user->current_tenant_id;
        }

        // Principal can only update pending payments for their centres
        if ($user->hasRole('principal')) {
            return $payment->tenant_id === $user->current_tenant_id &&
                   ($payment->centres->isEmpty() ||
                    $payment->centres->intersect($user->centres)->isNotEmpty()) &&
                   $payment->status === PaymentStatus::PENDING;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, Payment $payment): bool
    {
        // Only pending or failed payments can be deleted
        if (! in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::FAILED])) {
            return false;
        }

        // Super Admin and Admin can delete any eligible payment in their tenant
        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return $payment->tenant_id === $user->current_tenant_id;
        }

        // Principal can only delete pending/failed payments for their centres
        if ($user->hasRole('principal')) {
            return $payment->tenant_id === $user->current_tenant_id &&
                   ($payment->centres->isEmpty() ||
                    $payment->centres->intersect($user->centres)->isNotEmpty());
        }

        return false;
    }

    /**
     * Determine whether the user can delete any payments.
     */
    public function deleteAny(User $user): bool
    {
        // Only Super Admin and Admin can bulk delete payments
        return $user->hasAnyRole(['super-admin', 'admin']) &&
               $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can permanently delete the payment.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        // Only Super Admin can permanently delete payments
        return $user->hasRole('super-admin') &&
               $payment->tenant_id === $user->current_tenant_id;
    }

    /**
     * Determine whether the user can use bank transfer gateway.
     */
    public function useBankTransferGateway(User $user): bool
    {
        // Only Super Admin, Admin, and Principal can use bank transfer
        return $user->hasAnyRole(['super-admin', 'admin', 'principal']);
    }

    /**
     * Get available payment gateways for the user.
     */
    public function getAvailableGateways(User $user): array
    {
        $gateways = [
            Gateway::CHIP->value => 'CHIP',
        ];

        // Add bank transfer option only for authorized roles
        if ($this->useBankTransferGateway($user)) {
            $gateways[Gateway::BANK_TRANSFER->value] = 'Bank Transfer';
        }

        return $gateways;
    }
}
