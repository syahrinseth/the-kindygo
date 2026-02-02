<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to check if the current Sanctum token has specific abilities.
 */
class EnsureTokenHasAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$abilities  Required abilities (token must have ALL of them)
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error_code' => 'unauthenticated',
            ], 401);
        }

        $token = $user->currentAccessToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token.',
                'error_code' => 'invalid_token',
            ], 401);
        }

        // Check if token has all required abilities
        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token does not have the required permission.',
                    'error_code' => 'insufficient_permissions',
                    'required_ability' => $ability,
                ], 403);
            }
        }

        return $next($request);
    }
}
