<?php

use Illuminate\Support\Facades\Schedule;

// End chats whose participants went offline and purge expired presence rows.
// Requires the scheduler to run: `php artisan schedule:work` (or cron `schedule:run`).
Schedule::command('chats:cleanup-stale')->everyFiveMinutes()->withoutOverlapping();

// Delete messages 1 hour after a chat ends (kept while a report is open).
// See config/moderation.php; the landing page's privacy copy depends on this.
Schedule::command('chats:prune-messages')->everyFiveMinutes()->withoutOverlapping();
