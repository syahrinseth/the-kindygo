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

        // Update next_bill_date for enrolments by billing frequency
        $schedule->command('enrolments:update-next-bill-date-daily')
            ->dailyAt('00:01')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('enrolments:update-next-bill-date-weekly')
            ->weeklyOn(1, '00:01')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('enrolments:update-next-bill-date-monthly')
            ->monthlyOn(1, '00:01')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('enrolments:update-next-bill-date-quarterly')
            ->quarterly()
            ->at('00:01')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('enrolments:update-next-bill-date-yearly')
            ->yearlyOn(1, 1, '00:01')
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
