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
        
        // Generate scheduled invoices daily at 6 AM
        $schedule->command('invoices:generate-scheduled --days-ahead=7')
                 ->dailyAt('06:00')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('telescope:prune --hours=4320')->daily();
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
