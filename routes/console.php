<?php

use Illuminate\Support\Facades\Schedule;

// Cron calls schedule:run every minute, like on the hosting. Each call runs at
// most one due source; the lock outlives a crashed run by five minutes at most.
Schedule::command('sources:run-due')->everyMinute()->withoutOverlapping(5);

// Listings the LLM failed on get another try while they are fresh
Schedule::command('listings:retry-failed')->hourlyAt(30)->withoutOverlapping(5);
