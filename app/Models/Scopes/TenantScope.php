<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip scope when running in console/command context
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return;
        }
        
        $user = Auth::user();
        
        if (!$user || !$user->current_tenant_id) {
            $builder->whereRaw('1 = 0'); // Return empty result set if no current tenant
            return;
        }
        
        $builder->where($model->getTable() . '.tenant_id', $user->current_tenant_id);
    }
}
