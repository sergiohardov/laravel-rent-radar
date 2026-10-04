<?php

namespace App\Models;

use App\Sources\SourceName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Run state of a listing source: on/off, interval, last run and last error.
 */
#[Fillable([
    'name', 'enabled', 'interval_min_minutes', 'interval_max_minutes', 'next_run_at',
    'last_run_at', 'last_success_at', 'last_report',
    'last_error', 'last_error_at', 'consecutive_failures',
])]
class Source extends Model
{
    /**
     * Keep the UTC offset when writing timestampTz columns.
     */
    protected $dateFormat = 'Y-m-d H:i:sP';

    public static function for(SourceName $name): self
    {
        $source = static::query()->firstOrCreate(['name' => $name]);

        // A new row gets its interval defaults from the database
        return $source->wasRecentlyCreated ? $source->refresh() : $source;
    }

    public function isDue(CarbonImmutable $now): bool
    {
        return $this->enabled && ($this->next_run_at === null || $this->next_run_at->lte($now));
    }

    /**
     * Next run at a random moment inside the interval window.
     */
    public function scheduleNextRun(CarbonImmutable $now): void
    {
        $this->next_run_at = $now->addSeconds(random_int(
            $this->interval_min_minutes * 60,
            $this->interval_max_minutes * 60,
        ));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => SourceName::class,
            'enabled' => 'boolean',
            'interval_min_minutes' => 'integer',
            'interval_max_minutes' => 'integer',
            'next_run_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
            'last_success_at' => 'immutable_datetime',
            'last_report' => 'array',
            'last_error_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
        ];
    }
}
