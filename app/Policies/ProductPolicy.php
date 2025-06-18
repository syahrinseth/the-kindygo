<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        // Super Admin, Admin, Principal can view the list of products
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, Product $product): bool
    {
        // Check role-based permissions first
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            // Super Admin and Admin can view any product in their tenant
            return $product->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only view products for their centres or global products
        if ($user->hasRole('Principal')) {
            // Allow access if product belongs to their tenant
            if ($product->tenant_id !== $user->current_tenant_id) {
                return false;
            }
            
            // If product is assigned to specific centres, check if principal has access to any of those centres
            if ($product->centres->count() > 0) {
                return $user->centres()->whereIn('centres.id', $product->centres->pluck('id'))->exists();
            }
            
            // If product is not assigned to any specific centres (global product), allow access
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        // Super Admin, Admin, and Principal can create products
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
    }

    /**
     * Determine whether the user can update the product.
     */
    public function update(User $user, Product $product): bool
    {
        // Super Admin and Admin can update any product in their tenant
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $product->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only update products for their centres or global products
        if ($user->hasRole('Principal')) {
            // Check tenant first
            if ($product->tenant_id !== $user->current_tenant_id) {
                return false;
            }
            
            // If product is assigned to specific centres, check if principal has access to any of those centres
            if ($product->centres->count() > 0) {
                return $user->centres()->whereIn('centres.id', $product->centres->pluck('id'))->exists();
            }
            
            // If product is not assigned to any specific centres (global product), allow access
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Only draft or inactive products can be deleted
        if (!in_array($product->status, [\App\Enums\ProductStatus::DRAFT, \App\Enums\ProductStatus::INACTIVE])) {
            return false;
        }
        
        // Super Admin and Admin can delete any eligible product in their tenant
        if ($user->hasAnyRole(['Super Admin', 'Admin'])) {
            return $product->tenant_id === $user->current_tenant_id;
        }
        
        // Principal can only delete products for their centres or global products
        if ($user->hasRole('Principal')) {
            // Check tenant first
            if ($product->tenant_id !== $user->current_tenant_id) {
                return false;
            }
            
            // If product is assigned to specific centres, check if principal has access to any of those centres
            if ($product->centres->count() > 0) {
                return $user->centres()->whereIn('centres.id', $product->centres->pluck('id'))->exists();
            }
            
            // If product is not assigned to any specific centres (global product), allow access
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete any products.
     */
    public function deleteAny(User $user): bool
    {
        // Super Admin, Admin, and Principal can bulk delete products
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']) && 
               $user->current_tenant_id !== null;
    }

    /**
     * Determine whether the user can permanently delete the product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        // Only Super Admin can permanently delete products
        return $user->hasRole('Super Admin') && 
               $product->tenant_id === $user->current_tenant_id;
    }

    /**
     * Determine whether the user can restore the product.
     */
    public function restore(User $user, Product $product): bool
    {
        // Super Admin and Admin can restore products
        return $user->hasAnyRole(['Super Admin', 'Admin']) && 
               $product->tenant_id === $user->current_tenant_id;
    }
}
