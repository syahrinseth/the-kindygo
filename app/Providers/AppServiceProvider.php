<?php

namespace App\Providers;

use Filament\Panel;
use App\Models\Centre;
use Illuminate\Support\Facades\File;
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
        $this->registerObservers();
    }

    /**
     * Register all model observers dynamically.
     */
    protected function registerObservers(): void
    {
        $observersPath = app_path('Observers');

        if (!File::isDirectory($observersPath)) {
            return;
        }

        $observerFiles = File::files($observersPath);

        foreach ($observerFiles as $file) {
            // Get filename without extension (e.g., 'CentreObserver')
            $observerName = $file->getFilenameWithoutExtension();

            // Skip if it doesn't end with 'Observer'
            if (!str_ends_with($observerName, 'Observer')) {
                continue;
            }

            // Extract model name (e.g., 'Centre' from 'CentreObserver')
            $modelName = str_replace('Observer', '', $observerName);

            // Build fully qualified class names
            $observerClass = "App\\Observers\\{$observerName}";
            $modelClass = "App\\Models\\{$modelName}";

            // Check if both classes exist
            if (class_exists($observerClass) && class_exists($modelClass)) {
                $modelClass::observe($observerClass);
            }
        }
    }
}
