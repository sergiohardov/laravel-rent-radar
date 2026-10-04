<?php

namespace App\Sources;

use App\Models\Source;
use App\Pipeline\Pipeline;
use App\Pipeline\RunReport;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one source through the pipeline and keeps its run state: last run,
 * last error, failures in a row and the next run time.
 */
final class SourceRunner
{
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly SourceAlerts $alerts,
    ) {}

    /**
     * @throws Throwable the source's failure, after it is recorded
     */
    public function run(ListingSource $source): RunReport
    {
        $state = Source::for($source->name());
        $now = now()->toImmutable();
        $state->last_run_at = $now;
        $state->scheduleNextRun($now);

        try {
            $report = $this->pipeline->run($source);
        } catch (Throwable $e) {
            $state->fill([
                'last_error' => Str::limit(class_basename($e).': '.$e->getMessage(), 1000),
                'last_error_at' => now(),
                'consecutive_failures' => $state->consecutive_failures + 1,
            ])->save();
            $this->alerts->failed($state);

            throw $e;
        }

        $failuresBefore = $state->consecutive_failures;
        $state->fill([
            'last_success_at' => now(),
            'last_report' => (array) $report,
            'consecutive_failures' => 0,
        ])->save();
        $this->alerts->succeeded($state, $failuresBefore);

        return $report;
    }
}
