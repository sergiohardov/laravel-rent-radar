<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Sources\ListingSource;
use App\Sources\SourceHttp;
use App\Sources\SourceRegistry;
use App\Sources\SourceRunner;
use Illuminate\Console\Command;
use Throwable;

class RunDueSourceCommand extends Command
{
    protected $signature = 'sources:run-due';

    protected $description = 'Run the most overdue enabled source; the scheduler calls it every minute';

    public function handle(SourceRegistry $sources, SourceRunner $runner): int
    {
        // One source per call: a run may take up to the time budget, and the
        // next minute's call picks the next source.
        $source = $this->mostOverdue($sources->all());

        if ($source === null) {
            $this->info('No source is due.');

            return self::SUCCESS;
        }

        try {
            $report = $runner->run($source);
        } catch (Throwable $e) {
            report($e);
            $this->error("{$source->name()->value}: ".SourceHttp::redact(class_basename($e).': '.$e->getMessage()));

            return self::FAILURE;
        }

        $this->info(RunSourceCommand::summary($source->name(), $report));

        return self::SUCCESS;
    }

    /**
     * @param  list<ListingSource>  $sources
     */
    private function mostOverdue(array $sources): ?ListingSource
    {
        $now = now()->toImmutable();

        return collect($sources)
            ->map(fn (ListingSource $source) => ['source' => $source, 'state' => Source::for($source->name())])
            ->filter(fn (array $pair) => $pair['state']->isDue($now))
            ->sortBy(fn (array $pair) => $pair['state']->next_run_at?->getTimestamp() ?? 0)
            ->first()['source'] ?? null;
    }
}
