<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pipeline Run Limits
    |--------------------------------------------------------------------------
    |
    | A source run stops after this many already seen listings in a row: the
    | feed is newest first, so the rest is older. Bumped listings come back to
    | the top as seen ones, hence more than one.
    |
    | The time budget keeps a run inside the hosting's 60 s max_execution_time
    | with room for saving and logging.
    |
    */

    'stop_after_seen' => (int) env('SOURCES_STOP_AFTER_SEEN', 10),

    'time_budget_seconds' => (float) env('SOURCES_TIME_BUDGET_SECONDS', 45),

];
