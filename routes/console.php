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
Schedule::command('app:appointment-reminder')->hourly();
Schedule::command('ris:subscription-sweep')->dailyAt('03:10');

// Integration delivery queue: drain between cron ticks (cron-only deploy
// model — no long-running supervisor). --stop-when-empty exits after the
// backlog clears, so each minute spawns a short-lived worker.
Schedule::command('queue:work --stop-when-empty --max-time=50 --backoff=10')->everyMinute();
// NOTE: `model:prune` and the api.log trim were removed — no model is
// Prunable, and the APILog middleware was never attached to any route, so
// storage/logs/api.log can never exist for a trim job to manage.

Artisan::command('inspire', function () {
    $this->comment(Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');
