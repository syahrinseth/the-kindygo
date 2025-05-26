<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class UserCentreScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * Filters centres by both current tenant and user association.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        
        if (!$user || !$user->current_tenant_id) {
            $builder->whereRaw('1 = 0'); // Return empty result set if no current tenant
            return;
        }
        
        $builder->where('tenant_id', $user->current_tenant_id)
            ->whereHas('users', function (Builder $query) use ($user) {
                $query->where('users.id', $user->id);
            });
    }
}
