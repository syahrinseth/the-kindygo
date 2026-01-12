<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * Redirects authenticated users to their appropriate panel based on role:
     * - Admin roles (Super Admin, Admin, Principal, Teacher) → /admin
     * - Parent role (or no admin role) → / (parent panel)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Only redirect on the root path when user is an admin
        if ($request->is('/') && $user->isAdmin()) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
