<?php

use Illuminate\Support\Facades\Schedule;

// End chats whose participants went offline and purge expired presence rows.
// Requires the scheduler to run: `php artisan schedule:work` (or cron `schedule:run`).
Schedule::command('chats:cleanup-stale')->everyFiveMinutes()->withoutOverlapping();
