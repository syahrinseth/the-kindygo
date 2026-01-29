<?php

namespace App\Providers;

use App\Models\Centre;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

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
        // Customize email verification notification
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verify Your Email Address - '.config('app.name'))
                ->greeting('Hello '.$notifiable->name.'!')
                ->line('Thank you for registering with '.config('app.name').'.')
                ->line('Please click the button below to verify your email address and continue your registration.')
                ->action('Verify Email Address', $url)
                ->line('This link will expire in 60 minutes.')
                ->line('If you did not create an account, no further action is required.')
                ->salutation('Best regards, The '.config('app.name').' Team');
        });

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

        if (! File::isDirectory($observersPath)) {
            return;
        }

        $observerFiles = File::files($observersPath);

        foreach ($observerFiles as $file) {
            // Get filename without extension (e.g., 'CentreObserver')
            $observerName = $file->getFilenameWithoutExtension();

            // Skip if it doesn't end with 'Observer'
            if (! str_ends_with($observerName, 'Observer')) {
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
