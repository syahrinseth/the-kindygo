<?php

namespace App\Providers;

use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\Navigation\NavigationGroup;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
    }
}
