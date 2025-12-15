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

        // Only proceed if user is authenticated and we can determine a tenant
        if (Auth::check()) {
            $user = Auth::user();

            // Prefer Filament tenant, fall back to route parameter if necessary
            $currentTenant = Filament::getTenant();

            if (! $currentTenant) {
                $routeTenant = $request->route('tenant');

                if ($routeTenant instanceof \App\Models\Tenant) {
                    $currentTenant = $routeTenant;
                } elseif (is_string($routeTenant)) {
                    $currentTenant = \App\Models\Tenant::where('slug', $routeTenant)->first();
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
