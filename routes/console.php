<?php

use Illuminate\Support\Facades\Schedule;

// Cron calls schedule:run every minute, like on the hosting. Each call runs at
// most one due source; the lock outlives a crashed run by five minutes at most.
Schedule::command('sources:run-due')->everyMinute()->withoutOverlapping(5);

// Listings the LLM failed on get another try while they are fresh
Schedule::command('listings:retry-failed')->hourlyAt(30)->withoutOverlapping(5);

// Listing cards wait in the database queue; like the hosting cron, a short-lived worker
// drains it every minute
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(5);

// A source that runs without errors but brings nothing new is broken too
Schedule::command('sources:check-silence')->hourlyAt(15);
