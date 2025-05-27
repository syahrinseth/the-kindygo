<?php

namespace App\Models\Scopes;

use App\Enums\ChildStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ChildStatusScope implements Scope
{
    /**
     * @var ChildStatus|null
     */
    private ?ChildStatus $status;

    /**
     * Create a new scope instance.
     *
     * @param ChildStatus|null $status
     */
    public function __construct(?ChildStatus $status = null)
    {
        $this->status = $status;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        
        if (!$user || !$user->current_tenant_id) {
            return;
        }
        
        $builder->whereHas('tenants', function (Builder $query) use ($user) {
            $query->where('tenant_id', $user->current_tenant_id);
            
            if ($this->status !== null) {
                $query->where('status', $this->status);
            }
        });
    }
}
