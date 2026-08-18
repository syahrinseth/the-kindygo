<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantFromFilament
{
    /**
     * Handle an incoming request.
     *
     * This middleware syncs the Filament tenant with the user's current_tenant_id.
     * When a user switches tenants in Filament, this updates their current_tenant_id.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Filament::hasTenancy()) {
            Filament::setTenant(null, isQuiet: true);

            return $next($request);
        }

        $response = $next($request);

        // Only proceed if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $tenant = Filament::getTenant();

            // If Filament has a tenant set and it differs from user's current_tenant_id, update it
            if ($tenant && $user->current_tenant_id !== $tenant->id) {
                $user->update(['current_tenant_id' => $tenant->id]);
                $user->setCurrentTenant($tenant);
            }

            // If no Filament tenant is set but user has a current_tenant_id, set Filament tenant
            if (! $tenant && $user->current_tenant_id) {
                $currentTenant = $user->tenants()->find($user->current_tenant_id);
                if ($currentTenant) {
                    Filament::setTenant($currentTenant);
                }
            }
        }

        return $response;
    }
}
