<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class CurrentCentreDisplayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // FilamentView::registerRenderHook(
        //     'panels::topbar.logo.after',
        //     fn (): string => Blade::render('@php
        //         use Illuminate\Support\Facades\Auth;
        //         $user = Auth::user();
        //         $currentCentre = $user?->getCurrentCentre();
        //     @endphp

        //     @if ($currentCentre)
        //         <div class="py-1.5 ms-4 text-sm font-medium bg-amber-100 text-amber-800 rounded-md flex items-center">
        //             <strong>{{ $currentCentre->name }}</strong>
        //         </div>
        //     @endif')
        // );

        // Inject tenant menu next to logo so it's visible in the topbar
        FilamentView::registerRenderHook(
            'panels::topbar.logo.after',
            fn (): string => Blade::render('@if (filament()->hasTenancy() && filament()->hasTenantMenu())<div class="ms-4"><x-filament-panels::tenant-menu /></div>@endif')
        );
    }
}
