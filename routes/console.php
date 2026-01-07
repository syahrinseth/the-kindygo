<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule cleanup of abandoned registrations daily
Schedule::command('registrations:cleanup')
    ->daily()
    ->at('02:00')
    ->timezone('Asia/Kuala_Lumpur')
    ->emailOutputOnFailure(config('mail.from.address'));
