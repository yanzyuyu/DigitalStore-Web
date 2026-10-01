<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Requires a single cron entry on the host:
|
|   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
|
| withoutOverlapping() prevents a slow run from being started again while the
| previous one is still working; runInBackground() keeps the scheduler itself
| from blocking on it.
|
*/

// Expire unpaid orders so abandoned checkouts do not accumulate and the sales
// counters stay meaningful.
Schedule::command('orders:expire')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Prune expired Sanctum tokens daily; otherwise the table grows forever.
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->withoutOverlapping();
