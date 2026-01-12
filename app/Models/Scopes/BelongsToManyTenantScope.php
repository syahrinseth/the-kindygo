<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BelongsToManyTenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * This scope is for models that have a many-to-many relationship with tenants.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip scope when running in console/command context
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        $user = Auth::user();

        if (! $user || ! $user->current_tenant_id) {
            $builder->whereRaw('1 = 0'); // Return empty result set if no current tenant

            return;
        }

        // Join the pivot table and filter by the current tenant
        $builder->whereHas('tenants', function (Builder $query) use ($user) {
            $query->where('tenant_id', $user->current_tenant_id);
        });
    }
}
