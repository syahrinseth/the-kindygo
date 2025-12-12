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
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only proceed if user is authenticated and we have a Filament tenant
        if (Auth::check() && Filament::getTenant()) {
            $user = Auth::user();
            $currentTenant = Filament::getTenant();
            
            // Update the user's current_tenant_id if it has changed
            if ($user->current_tenant_id !== $currentTenant->id) {
                $user->update([
                    'current_tenant_id' => $currentTenant->id
                ]);
                
                // Also update the pivot table timestamp to track latest access
                $user->setCurrentTenant($currentTenant);
            }
        }

        return $response;
    }
}
