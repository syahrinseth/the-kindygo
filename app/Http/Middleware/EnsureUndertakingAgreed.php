<?php

namespace App\Http\Middleware;

use App\Actions\Undertaking\CheckParentUndertakingAgreementAction;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUndertakingAgreed
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Skip if user is not authenticated or not a parent
        if (! $user || ! $user->hasRole('Parent')) {
            return $next($request);
        }

        // Skip for specific routes
        $exemptPaths = [
            'parent/agreement/*',
            'parent/logout',
            '*/media/*',
            'filament/*/logout',
        ];

        foreach ($exemptPaths as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // Get current tenant
        $tenant = $user->currentTenant();
        if (! $tenant) {
            return $next($request);
        }

        // Check if parent has pending letter of undertaking
        $pendingLetter = app(CheckParentUndertakingAgreementAction::class)
            ->execute($user, $tenant);

        if ($pendingLetter) {
            // Redirect to agreement page if pending letter exists
            return redirect()->to('/parent/agreement/pending');
        }

        return $next($request);
    }
}
