<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowIncompleteRegistration
{
    /**
     * Handle an incoming request.
     *
     * Allow access if:
     * 1. User is a guest (not authenticated)
     * 2. User is authenticated but has incomplete profile (profile_completed = false)
     *
     * Redirect to dashboard if user is authenticated and has completed profile.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Allow guests to access registration
        if (! auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Admin roles always use the admin panel, even if they also have the Parent role.
        if ($user->isAdmin()) {
            return redirect('/admin');
        }

        // Only incomplete Parent users may continue registration.
        if ($user->requiresParentRegistration()) {
            return $next($request);
        }

        // Let the root route choose the appropriate destination for everyone else.
        return redirect('/');
    }
}
