<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Skip check if user is not authenticated
        if (! $user) {
            return $next($request);
        }

        // Only incomplete Parent users without an admin role use the registration wizard.
        if (! $user->requiresParentRegistration()) {
            return $next($request);
        }

        // Skip check if already on registration wizard or profile completion routes
        if ($request->is('register/*') ||
            $request->is('profile/complete') ||
            $request->is('profile/complete/*')) {
            return $next($request);
        }

        // Skip check for logout route, media routes, and API routes
        if ($request->is('logout') ||
            $request->is('media/*') ||
            $request->is('api/*') ||
            $request->is('livewire/*') ||
            $request->is('_debugbar/*')) {
            return $next($request);
        }

        // If user has a current tenant, redirect to wizard with current step
        if ($user->current_tenant_id) {
            $tenant = $user->currentTenant();

            return redirect()->route('tenant.register.form', [
                'tenant' => $tenant->slug,
                'step' => $user->getCurrentRegistrationStep(),
                'email' => $user->email,
            ]);
        }

        // Fallback to old profile completion page if no tenant
        return redirect()->route('profile.complete')
            ->with('warning', 'Please complete your profile to continue.');
    }
}
