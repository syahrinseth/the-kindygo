<?php

namespace App\Observers;

use App\Models\Product;

class ProductObserver
{
    public function creating(Product $product): void
    {
        if (empty($product->tenant_id)) {
            // Assign tenant_id before creating the product
            $product->tenant_id = auth()->user()?->currentTenant()?->id ?? 0;
        }
    }
}
