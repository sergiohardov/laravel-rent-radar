<?php

use Illuminate\Support\Facades\Schedule;

// Cron calls schedule:run every minute, like on the hosting. Each call runs at
// most one due source; the lock outlives a crashed run by five minutes at most.
Schedule::command('sources:run-due')->everyMinute()->withoutOverlapping(5);
