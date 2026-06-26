<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Scheduled tasks. Driven by a single cPanel cron entry:
 |   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
 | On hosts without per-minute cron, widen these intervals.
 */
Schedule::command('queue:work --stop-when-empty --max-time=55')->everyMinute()->withoutOverlapping();
Schedule::command('clicks:rollup')->everyTenMinutes()->withoutOverlapping();
Schedule::command('clicks:prune')->dailyAt('03:30');
Schedule::command('safety:rescan')->hourly()->withoutOverlapping();
Schedule::command('ai:weekly-insights')->weeklyOn(1, '07:00')->withoutOverlapping();
// Refresh the GeoIP database monthly (providers publish new data at the start of each month).
Schedule::command('geoip:update')->monthlyOn(3, '04:00')->withoutOverlapping();
// Keep the public demo fresh (no-op unless demo mode is on).
Schedule::command('demo:reset')->hourly()->withoutOverlapping();
