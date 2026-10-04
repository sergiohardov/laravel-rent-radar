<?php

namespace App\Console\Commands;

use App\Pipeline\RunReport;
use App\Sources\SourceName;
use App\Sources\SourceRegistry;
use App\Sources\SourceRunner;
use Illuminate\Console\Command;
use Throwable;

class RunSourceCommand extends Command
{
    protected $signature = 'sources:run {source : lun, olx or dimria}';

    protected $description = 'Fetch new listings from one source and run them through the pipeline';

    public function handle(SourceRegistry $sources, SourceRunner $runner): int
    {
        $name = SourceName::tryFrom((string) $this->argument('source'));

        if ($name === null) {
            $this->error('Unknown source. Use one of: '.implode(', ', array_column(SourceName::cases(), 'value')).'.');

            return self::INVALID;
        }

        try {
            $report = $runner->run($sources->get($name));
        } catch (Throwable $e) {
            report($e);
            $this->error("{$name->value}: ".class_basename($e).': '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(self::summary($name, $report));

        return self::SUCCESS;
    }

    public static function summary(SourceName $name, RunReport $report): string
    {
        return sprintf(
            '%s: fetched %d, seen %d, new %d, passed %d, rejected %d, stop: %s',
            $name->value,
            $report->fetched,
            $report->seen,
            $report->created,
            $report->passed,
            $report->rejected,
            $report->stopReason->value,
        );
    }
}
