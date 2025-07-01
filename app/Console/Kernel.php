<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run the update-overdue-invoices command daily at midnight
        $schedule->command('app:update-overdue-invoices')->daily();
        
        // Generate recurring invoices daily at 2 AM
        $schedule->command('invoices:generate-recurring')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->emailOutputOnFailure(config('mail.admin_email'));
                 
        // Optional: Generate one-time invoices weekly (for any missed enrollments)
        $schedule->command('invoices:generate-onetime')
                 ->weeklyOn(1, '03:00') // Monday at 3 AM
                 ->withoutOverlapping()
                 ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
