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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // Skip check if user is not authenticated
        if (!$user) {
            return $next($request);
        }
        
        // Skip check if user is not a Parent
        if (!$user->hasRole('Parent')) {
            return $next($request);
        }
        
        // Skip check if already on profile completion routes
        if ($request->is('profile/complete') || $request->is('profile/complete/*')) {
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
        
        // Check if profile is not completed
        if (!$user->profile_completed) {
            return redirect()->route('profile.complete')
                ->with('warning', 'Please complete your profile to continue.');
        }
        
        return $next($request);
    }
}
