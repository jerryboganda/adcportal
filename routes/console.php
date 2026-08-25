<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console / Scheduler
|--------------------------------------------------------------------------
*/

// Housekeeping (runs via `php artisan schedule:run` every minute in prod cron)
Schedule::command('sanctum:prune-expired --hours=24')->hourly();
Schedule::command('model:prune')->daily()->when(fn () => config('queue.default') !== 'sync');
Schedule::call(function () {
    // Trim API log files to last 30 days
    $log = storage_path('logs/api.log');
    if (is_file($log) && filemtime($log) < now()->subDays(30)->getTimestamp()) {
        @unlink($log);
    }
})->daily();

Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');
