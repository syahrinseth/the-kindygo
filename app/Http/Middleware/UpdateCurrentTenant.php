<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateCurrentTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only proceed if user is authenticated
        if (Auth::check()) {
            $user = Auth::user();

            // First prefer any Filament tenant (if present for specific panels)
            $currentTenant = Filament::getTenant();

            // If no tenant was provided via panel routing (we removed tenant slugs from URLs),
            // resolve the tenant from the user's defaults (personal or last used) or first attached.
            if (! $currentTenant) {
                $panel = Filament::getPanel();

                // Try the user's default tenant (uses user's personal tenant, latest accessed, first available)
                if (method_exists($user, 'getDefaultTenant')) {
                    $currentTenant = $user->getDefaultTenant($panel);
                }

                // Fallback to the first attached tenant
                if (! $currentTenant) {
                    $currentTenant = $user->tenants()->first();
                }
            }

            if ($currentTenant && $user->current_tenant_id !== $currentTenant->id) {
                $user->update([
                    'current_tenant_id' => $currentTenant->id,
                ]);

                // Also update the pivot table timestamp to track latest access
                $user->setCurrentTenant($currentTenant);
            }
        }

        return $response;
    }
}
