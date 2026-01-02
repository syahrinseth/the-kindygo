<?php

namespace App\Providers;

use Filament\Panel;
use App\Models\Centre;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Facades\FilamentView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure Filament panels
        Panel::configureUsing(function (Panel $panel): void {
            // Configure navigation groups
            $panel->navigationGroups([
                NavigationGroup::make()
                    ->label('Finance')
                    ->icon('heroicon-o-banknotes')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Campus Management')
                    ->icon('heroicon-o-academic-cap')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('User Management')
                    ->icon('heroicon-o-users')
                    ->collapsed(),
            ]);
        });

        // Model Observers can be registered here if needed
        Centre::observe(\App\Observers\CentreObserver::class);
    }
}
