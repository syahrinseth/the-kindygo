<?php

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;

class CurrentCentreDisplayPlugin implements Plugin
{
    public function getId(): string
    {
        return 'current-centre-display';
    }

    public function register(Panel $panel): void
    {
        // Register the view component
        FilamentView::registerRenderHook(
            'panels::topbar.start',
            fn (): string => Blade::render('@php
                use Illuminate\Support\Facades\Auth;
                $user = Auth::user();
                $currentCentre = $user?->currentCentre;
            @endphp

            @if ($currentCentre)
                <div class="px-2.5 py-1.5 ms-4 text-sm font-medium bg-amber-100 text-amber-800 rounded-md flex items-center">
                    <span class="text-xs opacity-75 mr-1">Centre:</span>
                    {{ $currentCentre->name }}
                </div>
            @endif')
        );
    }

    public function boot(Panel $panel): void
    {
        // No boot logic needed
    }
}
