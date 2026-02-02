<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure the authenticated user has access to the current tenant.
 */
class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error_code' => 'unauthenticated',
            ], 401);
        }

        // Check if user has a current tenant set
        if (! $user->current_tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'No organisation context. Please select an organisation.',
                'error_code' => 'no_tenant_context',
            ], 403);
        }

        // Verify user has access to the current tenant
        $tenant = Tenant::find($user->current_tenant_id);

        if (! $tenant || ! $user->canAccessTenant($tenant)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this organisation.',
                'error_code' => 'no_tenant_access',
            ], 403);
        }

        // Set the tenant in the request for downstream use
        $request->merge(['current_tenant' => $tenant]);

        return $next($request);
    }
}
