<?php

namespace App\Console\Commands;

use App\Pipeline\Pipeline;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use Illuminate\Console\Command;

class RunSourceCommand extends Command
{
    protected $signature = 'sources:run {source : lun, olx or dimria}';

    protected $description = 'Fetch new listings from one source and run them through the pipeline';

    public function handle(SourceRegistry $sources, Pipeline $pipeline): int
    {
        $name = SourceName::tryFrom((string) $this->argument('source'));

        if ($name === null) {
            $this->error('Unknown source. Use one of: '.implode(', ', array_column(SourceName::cases(), 'value')).'.');

            return self::INVALID;
        }

        $report = $pipeline->run($sources->get($name));

        $this->info(sprintf(
            '%s: fetched %d, seen %d, new %d, passed %d, rejected %d, stop: %s',
            $name->value,
            $report->fetched,
            $report->seen,
            $report->created,
            $report->passed,
            $report->rejected,
            $report->stopReason->value,
        ));

        return self::SUCCESS;
    }
}
